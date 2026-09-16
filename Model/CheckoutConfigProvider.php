<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Swissup\FreeShippingBar\Api\ThresholdResolverInterface;
use Swissup\FreeShippingBar\Model\Source\Placement;

/**
 * Publishes the resolved threshold into window.checkoutConfig so the checkout summary bar can
 * recompute locally on every coupon, qty or shipping change instead of waiting for a round trip.
 *
 * The threshold is converted to the display currency here, so the JS can compare it directly
 * against the display-currency totals it already has and never needs an exchange rate.
 */
class CheckoutConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ThresholdResolverInterface $thresholdResolver,
        private readonly CheckoutSession $checkoutSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $disabled = ['freeShippingBar' => ['enabled' => false]];

        try {
            $storeId = $this->storeManager->getStore()->getId();

            if (!$this->config->isEnabled($storeId)
                || !$this->config->isPlacementEnabled(Placement::CHECKOUT, $storeId)
            ) {
                return $disabled;
            }

            $quote = $this->checkoutSession->getQuote();

            if ($quote->getIsVirtual() || (int) $quote->getItemsCount() < 1) {
                return $disabled;
            }

            $threshold = $this->thresholdResolver->resolve($quote);

            if ($threshold === null || $threshold->getAmount() <= 0) {
                return $disabled;
            }

            return [
                'freeShippingBar' => [
                    'enabled' => true,
                    'threshold' => (float) $this->priceCurrency->convert(
                        $threshold->getAmount(),
                        $storeId
                    ),
                    'basis' => $threshold->getBasis() ?? $this->config->getBasis($storeId),
                    'includeTax' => $threshold->getIncludeTax() ?? $this->config->isIncludeTax($storeId),
                    'showWhenQualified' => $this->config->isShownWhenQualified($storeId),
                    'messageProgress' => (string) __($this->config->getProgressMessage($storeId)),
                    'messageQualified' => (string) __($this->config->getQualifiedMessage($storeId)),
                    'source' => $threshold->getSource(),
                ],
            ];
        } catch (\Exception $e) {
            return $disabled;
        }
    }
}
