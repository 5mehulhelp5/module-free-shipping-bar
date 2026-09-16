<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Threshold\Provider;

use Magento\Quote\Model\Quote;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;
use Swissup\FreeShippingBar\Api\ThresholdProviderInterface;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Data\Threshold;

/**
 * The manual per-customer-group table from admin. Always wins over auto-detection.
 */
class GroupOverrideProvider implements ThresholdProviderInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function getThreshold(Quote $quote): ?ThresholdInterface
    {
        $overrides = $this->config->getGroupOverrides($quote->getStoreId());

        if (!$overrides) {
            return null;
        }

        $groupId = (int) $quote->getCustomerGroupId();

        if (!isset($overrides[$groupId]) || $overrides[$groupId] <= 0) {
            return null;
        }

        return new Threshold($overrides[$groupId], 'group_override');
    }
}
