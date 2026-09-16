<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Api;

use Magento\Quote\Model\Quote;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;

/**
 * One source of a free shipping threshold.
 *
 * Typed against the quote model rather than CartInterface because the customer group id and the
 * address totals a threshold is judged against live on the model, not on the API interface.
 *
 * Providers are composed into a chain by Swissup\FreeShippingBar\Model\ThresholdResolver,
 * ordered by the sortOrder given in di.xml. The first provider returning a non-null
 * threshold wins, so a provider must return null rather than 0 when it has nothing to say.
 */
interface ThresholdProviderInterface
{
    public function getThreshold(Quote $quote): ?ThresholdInterface;
}
