<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;
use Swissup\FreeShippingBar\Api\ThresholdProviderInterface;
use Swissup\FreeShippingBar\Api\ThresholdResolverInterface;

/**
 * Asks each configured provider in turn and returns the first non-null threshold.
 *
 * Providers are declared in di.xml as
 * <item name="..." xsi:type="array">
 *     <item name="provider" xsi:type="object">...</item>
 *     <item name="sortOrder" xsi:type="number">10</item>
 * </item>
 * because di.xml only honours the sortOrder attribute for plugins, not for array items.
 */
class ThresholdResolver implements ThresholdResolverInterface
{
    /**
     * @var ThresholdProviderInterface[]|null
     */
    private ?array $sorted = null;

    /**
     * Shapes are not guaranteed: di.xml arrays are untyped, and a third party can add an entry.
     *
     * @param array<string, mixed> $providers
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $providers = []
    ) {
    }

    public function resolve(Quote $quote): ?ThresholdInterface
    {
        foreach ($this->getProviders() as $name => $provider) {
            try {
                $threshold = $provider->getThreshold($quote);
            } catch (LocalizedException | \Exception $e) {
                // A single misbehaving provider must never take the storefront down.
                $this->logger->error(
                    sprintf('Swissup_FreeShippingBar: provider "%s" failed: %s', $name, $e->getMessage()),
                    ['exception' => $e]
                );
                continue;
            }

            if ($threshold !== null && $threshold->getAmount() > 0) {
                return $threshold;
            }
        }

        return null;
    }

    /**
     * @return ThresholdProviderInterface[]
     * @throws LocalizedException
     */
    private function getProviders(): array
    {
        if ($this->sorted !== null) {
            return $this->sorted;
        }

        $rows = [];
        foreach ($this->providers as $name => $row) {
            $provider = is_array($row) ? ($row['provider'] ?? null) : $row;

            if (!$provider instanceof ThresholdProviderInterface) {
                throw new LocalizedException(
                    __('Threshold provider "%1" must implement ThresholdProviderInterface.', $name)
                );
            }

            $rows[] = [
                'name' => $name,
                'provider' => $provider,
                'sortOrder' => is_array($row) ? (int) ($row['sortOrder'] ?? 0) : 0,
            ];
        }

        usort($rows, static fn (array $a, array $b) => $a['sortOrder'] <=> $b['sortOrder']);

        $this->sorted = array_column($rows, 'provider', 'name');

        return $this->sorted;
    }
}
