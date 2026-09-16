<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Swissup\FreeShippingBar\Api\ThresholdResolverInterface;
use Swissup\FreeShippingBar\Model\Data\BarData;
use Swissup\FreeShippingBar\Model\Source\Basis;

/**
 * Turns a quote into everything the bar needs, or null when the bar must not render.
 */
class BarDataProvider
{
    /**
     * Magento compares money with this tolerance in Quote\Address::validateMinimumAmount(),
     * so a cart of exactly 1299.00 against a 1299 threshold qualifies here too.
     */
    private const EPSILON = 0.0001;

    public function __construct(
        private readonly Config $config,
        private readonly ThresholdResolverInterface $thresholdResolver,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function get(?Quote $quote): ?BarData
    {
        if ($quote === null) {
            return null;
        }

        $store = $quote->getStoreId();

        if (!$this->config->isEnabled($store)) {
            return null;
        }

        if ((int) $quote->getItemsCount() < 1) {
            return null;
        }

        // Nothing ships, so there is nothing to earn.
        if ($quote->getIsVirtual()) {
            return null;
        }

        $threshold = $this->thresholdResolver->resolve($quote);

        if ($threshold === null || $threshold->getAmount() <= 0) {
            return null;
        }

        $amount = $threshold->getAmount();
        $basis = $threshold->getBasis() ?? $this->config->getBasis($store);
        $includeTax = $threshold->getIncludeTax() ?? $this->config->isIncludeTax($store);

        $current = $this->getCurrentAmount($quote, $basis, $includeTax);
        $qualified = $this->hasFreeShipping($quote) || $current + self::EPSILON >= $amount;

        if ($qualified && !$this->config->isShownWhenQualified($store)) {
            return null;
        }

        $remaining = $qualified ? 0.0 : max(0.0, $amount - $current);
        $percent = $qualified ? 100.0 : max(0.0, min(100.0, $current / $amount * 100));

        $thresholdFormatted = $this->format($amount, $store);
        $remainingFormatted = $this->format($remaining, $store);

        $message = $qualified
            ? (string) __($this->config->getQualifiedMessage($store))
            : (string) __($this->config->getProgressMessage($store), $remainingFormatted);

        return new BarData(
            $amount,
            $current,
            $remaining,
            round($percent, 2),
            $qualified,
            $message,
            $thresholdFormatted,
            $remainingFormatted,
            $threshold->getSource(),
            $basis
        );
    }

    /**
     * All amounts are read in base currency so they can be compared against the stored threshold.
     */
    private function getCurrentAmount(Quote $quote, string $basis, bool $includeTax): float
    {
        if ($basis === Basis::GRAND_TOTAL) {
            // Grand total always includes tax; the includeTax flag does not apply.
            return (float) $quote->getBaseGrandTotal();
        }

        $total = 0.0;

        foreach ($this->getAddresses($quote) as $address) {
            $total += $basis === Basis::SUBTOTAL
                ? $this->getSubtotal($address, $includeTax)
                : $this->getSubtotalWithDiscount($address, $includeTax);
        }

        return $total;
    }

    private function getSubtotal(Address $address, bool $includeTax): float
    {
        if (!$includeTax) {
            return (float) $address->getBaseSubtotal();
        }

        // Set by Magento\Tax\Model\Sales\Total\Quote\Subtotal; fall back for carts collected
        // by a customised total collector that never populated it.
        $inclTax = $address->getBaseSubtotalTotalInclTax();

        return $inclTax !== null
            ? (float) $inclTax
            : (float) $address->getBaseSubtotal() + (float) $address->getBaseTaxAmount();
    }

    private function getSubtotalWithDiscount(Address $address, bool $includeTax): float
    {
        // Address::getBaseSubtotalWithDiscount() is base_subtotal + base_discount_amount
        // + base_shipping_discount_amount, with the discounts held as negative numbers.
        $value = (float) $address->getBaseSubtotalWithDiscount();

        // Matches Address::requestShippingRates(), which is what the Free Shipping carrier sees.
        return $includeTax ? $value + (float) $address->getBaseTaxAmount() : $value;
    }

    /**
     * @return Address[]
     */
    private function getAddresses(Quote $quote): array
    {
        if ($quote->getIsMultiShipping()) {
            return $quote->getAllShippingAddresses();
        }

        $address = $quote->getShippingAddress();

        return $address ? [$address] : [];
    }

    /**
     * A cart can already ship free without reaching the threshold — a free shipping coupon, or
     * per-item flags set by a cart price rule. Item flags only count when every shippable item
     * carries one; a mixed cart still pays for the rest of the shipment.
     */
    private function hasFreeShipping(Quote $quote): bool
    {
        foreach ($this->getAddresses($quote) as $address) {
            if ($address->getFreeShipping()) {
                return true;
            }
        }

        $shippable = 0;
        $free = 0;

        /** @var Item $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getProduct() && $item->getProduct()->getIsVirtual()) {
                continue;
            }

            $shippable++;

            if ($item->getFreeShipping()) {
                $free++;
            }
        }

        return $shippable > 0 && $shippable === $free;
    }

    private function format(float $amount, $store): string
    {
        return (string) $this->priceCurrency->convertAndFormat(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $store
        );
    }
}
