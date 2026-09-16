<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Threshold\Provider;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Quote\Model\Quote;
use Magento\Rule\Model\Condition\Combine;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Rule\Condition\Address as AddressCondition;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;
use Swissup\FreeShippingBar\Api\ThresholdProviderInterface;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Data\Threshold;
use Swissup\FreeShippingBar\Model\Source\Basis;

/**
 * Best-effort read of coupon-less Cart Price Rules that grant free shipping.
 *
 * Rule condition trees are arbitrarily nested and can express things a progress bar cannot
 * represent, so this provider is deliberately conservative: it only descends into AND branches,
 * only understands a single subtotal >= X leaf, and gives up silently on anything else. It is
 * off by default and is a convenience — never make it a dependency.
 */
class SalesRuleProvider implements ThresholdProviderInterface
{
    /**
     * Rule condition attribute => [basis, includeTax], mirroring
     * Magento\SalesRule\Model\Rule\Condition\Address::loadAttributeOptions().
     */
    private const SUBTOTAL_ATTRIBUTES = [
        'base_subtotal_with_discount' => [Basis::SUBTOTAL_WITH_DISCOUNT, false],
        'base_subtotal_total_incl_tax' => [Basis::SUBTOTAL, true],
        'base_subtotal' => [Basis::SUBTOTAL, false],
    ];

    private const SUPPORTED_OPERATORS = ['>=', '>'];

    /**
     * Guards against a pathological rule tree eating the request.
     */
    private const MAX_DEPTH = 5;

    public function __construct(
        private readonly Config $config,
        private readonly CollectionFactory $ruleCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ModuleManager $moduleManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getThreshold(Quote $quote): ?ThresholdInterface
    {
        $store = $quote->getStoreId();

        if (!$this->config->isAutodetectEnabled($store) || !$this->config->isSalesRuleParsingEnabled($store)) {
            return null;
        }

        if (!$this->moduleManager->isEnabled('Magento_SalesRule')) {
            return null;
        }

        $websiteId = (int) $this->storeManager->getStore($store)->getWebsiteId();
        $groupId = (int) $quote->getCustomerGroupId();

        $best = null;
        $bestBasis = null;
        $bestIncludeTax = null;
        $bestRuleId = 0;

        foreach ($this->getFreeShippingRules($websiteId, $groupId) as $rule) {
            $found = $this->findSubtotalCondition($rule);

            if ($found === null) {
                continue;
            }

            [$amount, $basis, $includeTax] = $found;

            if ($best === null || $amount < $best) {
                $best = $amount;
                $bestBasis = $basis;
                $bestIncludeTax = $includeTax;
                $bestRuleId = (int) $rule->getRuleId();
            }
        }

        if ($best === null) {
            return null;
        }

        return new Threshold($best, 'sales_rule_' . $bestRuleId, $bestBasis, $bestIncludeTax);
    }

    /**
     * @return Rule[]
     */
    private function getFreeShippingRules(int $websiteId, int $groupId): array
    {
        try {
            $collection = $this->ruleCollectionFactory->create()
                ->setValidationFilter($websiteId, $groupId, '')
                ->addFieldToFilter('simple_free_shipping', ['gt' => 0]);

            return array_values(array_filter(
                $collection->getItems(),
                static fn ($rule): bool => $rule instanceof Rule
            ));
        } catch (\Exception $e) {
            $this->logger->error(
                'Swissup_FreeShippingBar: could not load free shipping cart price rules: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return [];
        }
    }

    /**
     * @return array{0: float, 1: string, 2: bool}|null
     */
    private function findSubtotalCondition(Rule $rule): ?array
    {
        try {
            $conditions = $rule->getConditions();
        } catch (\Exception $e) {
            // Unparseable serialized conditions — skip this rule rather than fail the page.
            return null;
        }

        if (!$conditions instanceof Combine) {
            return null;
        }

        return $this->walk($conditions, 0);
    }

    /**
     * Descend AND branches only, returning the lowest subtotal threshold found.
     *
     * @return array{0: float, 1: string, 2: bool}|null
     */
    private function walk(Combine $combine, int $depth): ?array
    {
        if ($depth >= self::MAX_DEPTH) {
            return null;
        }

        // "If ANY of these conditions are TRUE" or a negated combine cannot be reduced to a
        // single threshold without changing its meaning.
        if ($combine->getAggregator() !== 'all' || !$combine->getValue()) {
            return null;
        }

        $best = null;

        foreach ((array) $combine->getConditions() as $condition) {
            $found = $condition instanceof Combine
                ? $this->walk($condition, $depth + 1)
                : $this->readLeaf($condition);

            if ($found !== null && ($best === null || $found[0] < $best[0])) {
                $best = $found;
            }
        }

        return $best;
    }

    /**
     * @return array{0: float, 1: string, 2: bool}|null
     */
    private function readLeaf(mixed $condition): ?array
    {
        if (!$condition instanceof AddressCondition) {
            return null;
        }

        $attribute = (string) $condition->getAttribute();

        if (!isset(self::SUBTOTAL_ATTRIBUTES[$attribute])) {
            return null;
        }

        if (!in_array((string) $condition->getOperator(), self::SUPPORTED_OPERATORS, true)) {
            return null;
        }

        $value = $condition->getValue();

        if (!is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        [$basis, $includeTax] = self::SUBTOTAL_ATTRIBUTES[$attribute];

        return [(float) $value, $basis, $includeTax];
    }
}
