<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Test\Unit\Model\Threshold\Provider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Source\Basis;
use Swissup\FreeShippingBar\Model\Threshold\Provider\CarrierFreeShippingProvider;

class CarrierFreeShippingProviderTest extends TestCase
{
    public function testPicksTheLowestThresholdAcrossActiveCarriers(): void
    {
        $threshold = $this->resolve(true, [
            'tablerate' => ['active' => '1', 'free_shipping_enable' => '1', 'free_shipping_subtotal' => '2000'],
            'ups' => ['active' => '1', 'free_shipping_enable' => '1', 'free_shipping_subtotal' => '1500'],
        ]);

        $this->assertNotNull($threshold);
        $this->assertSame(1500.0, $threshold->getAmount());
        $this->assertSame('carrier_ups', $threshold->getSource());
        $this->assertSame(Basis::SUBTOTAL_WITH_DISCOUNT, $threshold->getBasis());
        $this->assertFalse($threshold->getIncludeTax());
    }

    public function testSkipsInactiveCarriersAndCarriersWithoutTheFlag(): void
    {
        $threshold = $this->resolve(true, [
            'tablerate' => ['active' => '0', 'free_shipping_enable' => '1', 'free_shipping_subtotal' => '500'],
            'flatrate' => ['active' => '1', 'free_shipping_enable' => '0', 'free_shipping_subtotal' => '600'],
            'ups' => ['active' => '1', 'free_shipping_enable' => '1', 'free_shipping_subtotal' => '1500'],
        ]);

        $this->assertNotNull($threshold);
        $this->assertSame(1500.0, $threshold->getAmount());
    }

    public function testLeavesTheFreeshippingCarrierToItsOwnProvider(): void
    {
        $this->assertNull($this->resolve(true, [
            'freeshipping' => ['active' => '1', 'free_shipping_enable' => '1', 'free_shipping_subtotal' => '100'],
        ]));
    }

    public function testDoesNothingWhenAutodetectionIsOff(): void
    {
        $this->assertNull($this->resolve(false, [
            'ups' => ['active' => '1', 'free_shipping_enable' => '1', 'free_shipping_subtotal' => '1500'],
        ]));
    }

    /**
     * @param array<string, array<string, string>> $carriers
     */
    private function resolve(bool $autodetect, array $carriers): ?\Swissup\FreeShippingBar\Api\Data\ThresholdInterface
    {
        $config = $this->createMock(Config::class);
        $config->method('isAutodetectEnabled')->willReturn($autodetect);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($carriers);

        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);

        return (new CarrierFreeShippingProvider($config, $scopeConfig))->getThreshold($quote);
    }
}
