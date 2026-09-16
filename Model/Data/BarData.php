<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Data;

/**
 * Everything a surface needs to render the bar. Amounts are in base currency;
 * the *Formatted values are already converted to the quote display currency.
 */
class BarData
{
    public function __construct(
        private readonly float $threshold,
        private readonly float $current,
        private readonly float $remaining,
        private readonly float $percent,
        private readonly bool $qualified,
        private readonly string $message,
        private readonly string $thresholdFormatted,
        private readonly string $remainingFormatted,
        private readonly string $source,
        private readonly string $basis
    ) {
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function getCurrent(): float
    {
        return $this->current;
    }

    public function getRemaining(): float
    {
        return $this->remaining;
    }

    public function getPercent(): float
    {
        return $this->percent;
    }

    public function isQualified(): bool
    {
        return $this->qualified;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getThresholdFormatted(): string
    {
        return $this->thresholdFormatted;
    }

    public function getRemainingFormatted(): string
    {
        return $this->remainingFormatted;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getBasis(): string
    {
        return $this->basis;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'threshold' => $this->threshold,
            'current' => $this->current,
            'remaining' => $this->remaining,
            'percent' => $this->percent,
            'qualified' => $this->qualified,
            'message' => $this->message,
            'threshold_formatted' => $this->thresholdFormatted,
            'remaining_formatted' => $this->remainingFormatted,
            'source' => $this->source,
            'basis' => $this->basis,
        ];
    }
}
