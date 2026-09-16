<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Swissup\FreeShippingBar\Model\Source\Basis;

class Config
{
    public const XML_PATH_ENABLED = 'freeshippingbar/general/enabled';
    public const XML_PATH_PLACEMENTS = 'freeshippingbar/general/placements';
    public const XML_PATH_BASIS = 'freeshippingbar/general/basis';
    public const XML_PATH_INCLUDE_TAX = 'freeshippingbar/general/include_tax';

    public const XML_PATH_AUTODETECT = 'freeshippingbar/thresholds/autodetect';
    public const XML_PATH_PARSE_SALES_RULES = 'freeshippingbar/thresholds/parse_sales_rules';
    public const XML_PATH_GROUP_OVERRIDES = 'freeshippingbar/thresholds/group_overrides';

    public const XML_PATH_SHOW_WHEN_QUALIFIED = 'freeshippingbar/appearance/show_when_qualified';
    public const XML_PATH_MESSAGE_PROGRESS = 'freeshippingbar/appearance/message_progress';
    public const XML_PATH_MESSAGE_QUALIFIED = 'freeshippingbar/appearance/message_qualified';
    public const XML_PATH_COLOR_TRACK = 'freeshippingbar/appearance/color_track';
    public const XML_PATH_COLOR_FILL = 'freeshippingbar/appearance/color_fill';
    public const XML_PATH_COLOR_SUCCESS = 'freeshippingbar/appearance/color_success';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    public function isEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * @return string[]
     */
    public function getPlacements($store = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PLACEMENTS,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $placement): bool => $placement !== ''
        ));
    }

    public function isPlacementEnabled(string $placement, $store = null): bool
    {
        return in_array($placement, $this->getPlacements($store), true);
    }

    public function getBasis($store = null): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_BASIS, ScopeInterface::SCOPE_STORE, $store);

        return $value !== '' ? $value : Basis::SUBTOTAL_WITH_DISCOUNT;
    }

    public function isIncludeTax($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_TAX, ScopeInterface::SCOPE_STORE, $store);
    }

    public function isAutodetectEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_AUTODETECT, ScopeInterface::SCOPE_STORE, $store);
    }

    public function isSalesRuleParsingEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PARSE_SALES_RULES,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Customer group id => threshold amount, in base currency.
     *
     * @return array<int, float>
     */
    public function getGroupOverrides($store = null): array
    {
        $raw = $this->scopeConfig->getValue(
            self::XML_PATH_GROUP_OVERRIDES,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $rows = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['customer_group'], $row['amount'])) {
                continue;
            }

            if (!is_numeric($row['amount'])) {
                continue;
            }

            $result[(int) $row['customer_group']] = (float) $row['amount'];
        }

        return $result;
    }

    public function isShownWhenQualified($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_WHEN_QUALIFIED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getProgressMessage($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_MESSAGE_PROGRESS,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getQualifiedMessage($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_MESSAGE_QUALIFIED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @return array<string, string>
     */
    public function getColors($store = null): array
    {
        return [
            'track' => (string) $this->scopeConfig->getValue(
                self::XML_PATH_COLOR_TRACK,
                ScopeInterface::SCOPE_STORE,
                $store
            ),
            'fill' => (string) $this->scopeConfig->getValue(
                self::XML_PATH_COLOR_FILL,
                ScopeInterface::SCOPE_STORE,
                $store
            ),
            'success' => (string) $this->scopeConfig->getValue(
                self::XML_PATH_COLOR_SUCCESS,
                ScopeInterface::SCOPE_STORE,
                $store
            ),
        ];
    }
}
