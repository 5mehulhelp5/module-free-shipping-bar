<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Api\Data;

/**
 * A resolved free shipping threshold, expressed in the website base currency.
 */
interface ThresholdInterface
{
    /**
     * Threshold amount in base currency.
     */
    public function getAmount(): float;

    /**
     * Basis this threshold is measured against, or null to use the configured default.
     *
     * One of \Swissup\FreeShippingBar\Model\Source\Basis constants.
     */
    public function getBasis(): ?string;

    /**
     * Whether the source measures the basis including tax, or null to use the configured default.
     */
    public function getIncludeTax(): ?bool;

    /**
     * Human readable origin of the value, e.g. "group_override", shown in admin hints and logs.
     */
    public function getSource(): string;
}
