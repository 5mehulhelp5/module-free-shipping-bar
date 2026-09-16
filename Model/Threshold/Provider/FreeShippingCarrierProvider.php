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
 * The "Free Shipping" offline carrier.
 *
 * Magento\OfflineShipping\Model\Carrier\Freeshipping compares free_shipping_subtotal against
 * base_subtotal_with_discount_incl_tax, which Magento\Quote\Model\Quote\Address defines as
 * base_subtotal_with_discount + base_tax_amount — hence basis and includeTax are reported
 * explicitly and override whatever the admin picked as the global default.
 */
class FreeShippingCarrierProvider implements ThresholdProviderInterface
{
    private const XML_PATH_ACTIVE = 'carriers/freeshipping/active';
    private const XML_PATH_SUBTOTAL = 'carriers/freeshipping/free_shipping_subtotal';

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

        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $store)) {
            return null;
        }

        $amount = (float) $this->scopeConfig->getValue(
            self::XML_PATH_SUBTOTAL,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        if ($amount <= 0) {
            return null;
        }

        return new Threshold($amount, 'carrier_freeshipping', Basis::SUBTOTAL_WITH_DISCOUNT, true);
    }
}
