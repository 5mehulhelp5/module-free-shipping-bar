<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Threshold\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;
use Swissup\FreeShippingBar\Api\ThresholdProviderInterface;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Data\Threshold;
use Swissup\FreeShippingBar\Model\Source\Basis;

/**
 * Generic scan of every active carrier for the free_shipping_enable / free_shipping_subtotal pair
 * that Magento\Shipping\Model\Carrier\AbstractCarrierOnline defines — tablerate, ups, usps, fedex,
 * dhl and any third-party carrier following the same convention. Lowest threshold wins, since that
 * is the first one the customer will reach.
 *
 * The whole carriers branch is read in one go instead of instantiating carrier models, which keeps
 * this cheap enough to run on every minicart section load.
 */
class CarrierFreeShippingProvider implements ThresholdProviderInterface
{
    private const XML_PATH_CARRIERS = 'carriers';

    /**
     * Handled by FreeShippingCarrierProvider, which knows its exact tax semantics.
     */
    private const SKIP_CARRIERS = ['freeshipping'];

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getThreshold(Quote $quote): ?ThresholdInterface
    {
        $store = $quote->getStoreId();

        if (!$this->config->isAutodetectEnabled($store)) {
            return null;
        }

        $carriers = $this->scopeConfig->getValue(self::XML_PATH_CARRIERS, ScopeInterface::SCOPE_STORE, $store);

        if (!is_array($carriers)) {
            return null;
        }

        $lowest = null;
        $lowestCode = '';

        foreach ($carriers as $code => $settings) {
            if (!is_array($settings) || in_array($code, self::SKIP_CARRIERS, true)) {
                continue;
            }

            if (empty($settings['active']) || empty($settings['free_shipping_enable'])) {
                continue;
            }

            $amount = isset($settings['free_shipping_subtotal']) ? (float) $settings['free_shipping_subtotal'] : 0.0;

            if ($amount <= 0) {
                continue;
            }

            if ($lowest === null || $amount < $lowest) {
                $lowest = $amount;
                $lowestCode = (string) $code;
            }
        }

        if ($lowest === null) {
            return null;
        }

        // AbstractCarrierOnline::getMethodPrice() compares against the package value with discount,
        // which is exclusive of tax.
        return new Threshold($lowest, 'carrier_' . $lowestCode, Basis::SUBTOTAL_WITH_DISCOUNT, false);
    }
}
