<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Source\Basis;
use Swissup\FreeShippingBar\Model\Source\Placement;

class ConfigTest extends TestCase
{
    public function testReadsTheOverrideTableIntoAGroupIdMap(): void
    {
        $raw = '[{"customer_group":"0","amount":"1299"},{"customer_group":"4","amount":"2500"}]';

        $this->assertSame([0 => 1299.0, 4 => 2500.0], $this->config($raw)->getGroupOverrides(1));
    }

    public function testMalformedJsonIsTreatedAsNoOverrides(): void
    {
        $this->assertSame([], $this->config('not json at all')->getGroupOverrides(1));
    }

    public function testEmptyConfigIsTreatedAsNoOverrides(): void
    {
        $this->assertSame([], $this->config('')->getGroupOverrides(1));
    }

    public function testRowsMissingAGroupOrANumericAmountAreDropped(): void
    {
        $raw = '[{"customer_group":"0"},{"amount":"1299"},{"customer_group":"4","amount":"abc"},'
            . '{"customer_group":"5","amount":"999"}]';

        $this->assertSame([5 => 999.0], $this->config($raw)->getGroupOverrides(1));
    }

    public function testPlacementsAreSplitFromTheMultiselectValue(): void
    {
        $config = $this->config('', ['freeshippingbar/general/placements' => 'minicart,cart']);

        $this->assertSame([Placement::MINICART, Placement::CART], $config->getPlacements(1));
        $this->assertTrue($config->isPlacementEnabled(Placement::CART, 1));
        $this->assertFalse($config->isPlacementEnabled(Placement::CHECKOUT, 1));
    }

    public function testNoPlacementSelectedMeansNoPlacementEnabled(): void
    {
        $config = $this->config('', ['freeshippingbar/general/placements' => '']);

        $this->assertSame([], $config->getPlacements(1));
        $this->assertFalse($config->isPlacementEnabled(Placement::MINICART, 1));
    }

    public function testBasisFallsBackToSubtotalAfterDiscount(): void
    {
        $this->assertSame(Basis::SUBTOTAL_WITH_DISCOUNT, $this->config('')->getBasis(1));
    }

    /**
     * @param array<string, string> $extra
     */
    private function config(string $groupOverrides, array $extra = []): Config
    {
        $values = $extra + [Config::XML_PATH_GROUP_OVERRIDES => $groupOverrides];

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(static fn (string $path) => $values[$path] ?? null);

        return new Config($scopeConfig, new Json());
    }
}
