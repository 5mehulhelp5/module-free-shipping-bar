<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Test\Unit\Model;

use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;
use Swissup\FreeShippingBar\Api\ThresholdProviderInterface;
use Swissup\FreeShippingBar\Model\Data\Threshold;
use Swissup\FreeShippingBar\Model\ThresholdResolver;

class ThresholdResolverTest extends TestCase
{
    private Quote&MockObject $quote;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->quote = $this->createMock(Quote::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testReturnsNullWithoutProviders(): void
    {
        $this->assertNull((new ThresholdResolver($this->logger))->resolve($this->quote));
    }

    public function testLowestSortOrderWinsRegardlessOfDeclarationOrder(): void
    {
        $resolver = new ThresholdResolver($this->logger, [
            'late' => ['provider' => $this->provider(new Threshold(500.0, 'late')), 'sortOrder' => 30],
            'early' => ['provider' => $this->provider(new Threshold(1299.0, 'early')), 'sortOrder' => 10],
        ]);

        $threshold = $resolver->resolve($this->quote);

        $this->assertNotNull($threshold);
        $this->assertSame('early', $threshold->getSource());
        $this->assertSame(1299.0, $threshold->getAmount());
    }

    public function testFallsThroughProvidersThatHaveNothingToSay(): void
    {
        $resolver = new ThresholdResolver($this->logger, [
            'null' => ['provider' => $this->provider(null), 'sortOrder' => 10],
            'zero' => ['provider' => $this->provider(new Threshold(0.0, 'zero')), 'sortOrder' => 20],
            'real' => ['provider' => $this->provider(new Threshold(2500.0, 'real')), 'sortOrder' => 30],
        ]);

        $threshold = $resolver->resolve($this->quote);

        $this->assertNotNull($threshold);
        $this->assertSame('real', $threshold->getSource());
    }

    public function testAThrowingProviderIsLoggedAndSkipped(): void
    {
        $broken = $this->createMock(ThresholdProviderInterface::class);
        $broken->method('getThreshold')->willThrowException(new \RuntimeException('boom'));

        $this->logger->expects($this->once())->method('error');

        $resolver = new ThresholdResolver($this->logger, [
            'broken' => ['provider' => $broken, 'sortOrder' => 10],
            'good' => ['provider' => $this->provider(new Threshold(1299.0, 'good')), 'sortOrder' => 20],
        ]);

        $threshold = $resolver->resolve($this->quote);

        $this->assertNotNull($threshold);
        $this->assertSame('good', $threshold->getSource());
    }

    public function testRejectsAnythingThatIsNotAProvider(): void
    {
        $resolver = new ThresholdResolver($this->logger, [
            'bogus' => ['provider' => new \stdClass(), 'sortOrder' => 10],
        ]);

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        $resolver->resolve($this->quote);
    }

    private function provider(?ThresholdInterface $threshold): ThresholdProviderInterface
    {
        $provider = $this->createMock(ThresholdProviderInterface::class);
        $provider->method('getThreshold')->willReturn($threshold);

        return $provider;
    }
}
