<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Test\Unit\Model;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Swissup\FreeShippingBar\Api\ThresholdResolverInterface;
use Swissup\FreeShippingBar\Model\BarDataProvider;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Data\Threshold;
use Swissup\FreeShippingBar\Model\Source\Basis;

class BarDataProviderTest extends TestCase
{
    private const THRESHOLD = 1299.0;

    private Config&MockObject $config;

    private ThresholdResolverInterface&MockObject $resolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShownWhenQualified')->willReturn(true);
        $this->config->method('getBasis')->willReturn(Basis::SUBTOTAL_WITH_DISCOUNT);
        $this->config->method('isIncludeTax')->willReturn(false);
        $this->config->method('getProgressMessage')->willReturn('You are %1 away from free shipping.');
        $this->config->method('getQualifiedMessage')->willReturn('Congratulations! You have earned free shipping.');

        $this->resolver = $this->createMock(ThresholdResolverInterface::class);
        $this->resolver->method('resolve')->willReturn(new Threshold(self::THRESHOLD, 'group_override'));
    }

    public function testComputesRemainingAndPercentFromSubtotalAfterDiscount(): void
    {
        $data = $this->build($this->quote($this->address(919.0)));

        $this->assertNotNull($data);
        $this->assertSame(919.0, $data->getCurrent());
        $this->assertSame(380.0, $data->getRemaining());
        $this->assertSame(70.75, $data->getPercent());
        $this->assertFalse($data->isQualified());
        $this->assertSame('You are $380.00 away from free shipping.', $data->getMessage());
    }

    /**
     * The boundary Floriteshop will be looking at: a cart of exactly 1299.00 has qualified.
     */
    public function testExactlyOnTheThresholdQualifies(): void
    {
        $data = $this->build($this->quote($this->address(1299.0)));

        $this->assertNotNull($data);
        $this->assertTrue($data->isQualified());
        $this->assertSame(0.0, $data->getRemaining());
        $this->assertSame(100.0, $data->getPercent());
        $this->assertSame('Congratulations! You have earned free shipping.', $data->getMessage());
    }

    public function testOvershootingTheThresholdNeverExceedsFullWidth(): void
    {
        $data = $this->build($this->quote($this->address(5000.0)));

        $this->assertNotNull($data);
        $this->assertTrue($data->isQualified());
        $this->assertSame(100.0, $data->getPercent());
    }

    public function testHiddenOnceQualifiedWhenAdminAsksForThat(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isShownWhenQualified')->willReturn(false);
        $config->method('getBasis')->willReturn(Basis::SUBTOTAL_WITH_DISCOUNT);
        $config->method('isIncludeTax')->willReturn(false);

        $this->assertNull($this->build($this->quote($this->address(1299.0)), $config));
    }

    public function testAFreeShippingCouponCountsAsQualified(): void
    {
        $data = $this->build($this->quote($this->address(100.0, 0.0, true)));

        $this->assertNotNull($data);
        $this->assertTrue($data->isQualified());
        $this->assertSame(100.0, $data->getPercent());
    }

    public function testHiddenForAnEmptyCart(): void
    {
        $this->assertNull($this->build($this->quote($this->address(0.0), 0)));
    }

    public function testHiddenForAVirtualCart(): void
    {
        $this->assertNull($this->build($this->quote($this->address(500.0), 1, true)));
    }

    public function testHiddenWhenNoThresholdResolves(): void
    {
        $resolver = $this->createMock(ThresholdResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);

        $provider = new BarDataProvider($this->config, $resolver, $this->priceCurrency());

        $this->assertNull($provider->get($this->quote($this->address(500.0))));
    }

    public function testIncludeTaxAddsTheCartTaxToTheBasis(): void
    {
        $address = $this->address(1200.0, 99.0);

        $resolver = $this->createMock(ThresholdResolverInterface::class);
        $resolver->method('resolve')->willReturn(
            new Threshold(self::THRESHOLD, 'carrier_freeshipping', Basis::SUBTOTAL_WITH_DISCOUNT, true)
        );

        $provider = new BarDataProvider($this->config, $resolver, $this->priceCurrency());
        $data = $provider->get($this->quote($address));

        $this->assertNotNull($data);
        $this->assertSame(1299.0, $data->getCurrent());
        $this->assertTrue($data->isQualified());
    }

    public function testAmountsAreShownInTheDisplayCurrency(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndFormat')
            ->willReturnCallback(
                // 20 base units to the display unit, so 380.00 base reads as 19.00 on screen.
                static fn (float $amount): string => '€' . number_format($amount / 20, 2)
            );

        $provider = new BarDataProvider($this->config, $this->resolver, $priceCurrency);
        $data = $provider->get($this->quote($this->address(919.0)));

        $this->assertNotNull($data);
        $this->assertSame(380.0, $data->getRemaining(), 'the raw amount stays in base currency');
        $this->assertSame('€19.00', $data->getRemainingFormatted());
        $this->assertSame('You are €19.00 away from free shipping.', $data->getMessage());
    }

    private function build(Quote $quote, ?Config $config = null): ?\Swissup\FreeShippingBar\Model\Data\BarData
    {
        $provider = new BarDataProvider($config ?? $this->config, $this->resolver, $this->priceCurrency());

        return $provider->get($quote);
    }

    private function priceCurrency(): PriceCurrencyInterface&MockObject
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndFormat')
            ->willReturnCallback(static fn (float $amount): string => '$' . number_format($amount, 2));

        return $priceCurrency;
    }

    private function address(
        float $subtotalWithDiscount,
        float $taxAmount = 0.0,
        bool $freeShipping = false
    ): Address&MockObject {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseSubtotalWithDiscount'])
            ->addMethods(['getBaseTaxAmount', 'getFreeShipping'])
            ->getMock();

        $address->method('getBaseSubtotalWithDiscount')->willReturn($subtotalWithDiscount);
        $address->method('getBaseTaxAmount')->willReturn($taxAmount);
        $address->method('getFreeShipping')->willReturn($freeShipping);

        return $address;
    }

    private function quote(Address $address, int $itemsCount = 1, bool $isVirtual = false): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'getItemsCount', 'getIsVirtual', 'getShippingAddress', 'getAllVisibleItems'])
            ->addMethods(['getIsMultiShipping'])
            ->getMock();

        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getItemsCount')->willReturn($itemsCount);
        $quote->method('getIsVirtual')->willReturn($isVirtual);
        $quote->method('getIsMultiShipping')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getAllVisibleItems')->willReturn([]);

        return $quote;
    }
}
