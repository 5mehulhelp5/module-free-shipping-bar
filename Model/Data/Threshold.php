<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Data;

use Swissup\FreeShippingBar\Api\Data\ThresholdInterface;

class Threshold implements ThresholdInterface
{
    public function __construct(
        private readonly float $amount,
        private readonly string $source,
        private readonly ?string $basis = null,
        private readonly ?bool $includeTax = null
    ) {
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBasis(): ?string
    {
        return $this->basis;
    }

    public function getIncludeTax(): ?bool
    {
        return $this->includeTax;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
