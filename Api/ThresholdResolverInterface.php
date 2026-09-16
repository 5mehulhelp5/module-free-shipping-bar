<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Api;

use Magento\Quote\Model\Quote;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;

interface ThresholdResolverInterface
{
    /**
     * Resolve the threshold that applies to the given quote, or null when none does.
     */
    public function resolve(Quote $quote): ?ThresholdInterface;
}
