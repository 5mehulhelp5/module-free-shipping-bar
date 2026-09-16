<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Test\Unit\Model\Threshold\Provider;

use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Threshold\Provider\GroupOverrideProvider;

class GroupOverrideProviderTest extends TestCase
{
    /**
     * The two numbers Floriteshop actually runs: guests at 1299, Mayoreo at 2500.
     */
    private const OVERRIDES = [0 => 1299.0, 4 => 2500.0];

    public function testPicksTheThresholdForTheQuoteCustomerGroup(): void
    {
        $threshold = $this->resolve(4, self::OVERRIDES);

        $this->assertNotNull($threshold);
        $this->assertSame(2500.0, $threshold->getAmount());
        $this->assertSame('group_override', $threshold->getSource());
    }

    public function testGuestQuotesUseTheNotLoggedInGroup(): void
    {
        $threshold = $this->resolve(0, self::OVERRIDES);

        $this->assertNotNull($threshold);
        $this->assertSame(1299.0, $threshold->getAmount());
    }

    public function testDefersToTheNextProviderForAnUnlistedGroup(): void
    {
        $this->assertNull($this->resolve(7, self::OVERRIDES));
    }

    public function testDefersWhenNothingIsConfigured(): void
    {
        $this->assertNull($this->resolve(0, []));
    }

    public function testIgnoresANonPositiveOverride(): void
    {
        $this->assertNull($this->resolve(0, [0 => 0.0]));
    }

    /**
     * @param array<int, float> $overrides
     */
    private function resolve(int $groupId, array $overrides): ?\Swissup\FreeShippingBar\Api\Data\ThresholdInterface
    {
        $config = $this->createMock(Config::class);
        $config->method('getGroupOverrides')->willReturn($overrides);

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getCustomerGroupId')->willReturn($groupId);

        return (new GroupOverrideProvider($config))->getThreshold($quote);
    }
}
