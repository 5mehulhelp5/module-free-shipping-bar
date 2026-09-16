<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Basis implements OptionSourceInterface
{
    public const SUBTOTAL_WITH_DISCOUNT = 'subtotal_with_discount';
    public const SUBTOTAL = 'subtotal';
    public const GRAND_TOTAL = 'grand_total';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::SUBTOTAL_WITH_DISCOUNT,
                'label' => __('Subtotal after discount'),
            ],
            [
                'value' => self::SUBTOTAL,
                'label' => __('Subtotal before discount'),
            ],
            [
                'value' => self::GRAND_TOTAL,
                'label' => __('Grand total'),
            ],
        ];
    }
}
