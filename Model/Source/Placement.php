<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Placement implements OptionSourceInterface
{
    public const MINICART = 'minicart';
    public const CART = 'cart';
    public const CHECKOUT = 'checkout';
    public const AJAXPRO_POPUP = 'ajaxpro_popup';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::MINICART,
                'label' => __('Minicart / cart sidebar'),
            ],
            [
                'value' => self::CART,
                'label' => __('Shopping cart page'),
            ],
            [
                'value' => self::CHECKOUT,
                'label' => __('Checkout order summary'),
            ],
            [
                'value' => self::AJAXPRO_POPUP,
                'label' => __('Ajax Cart Pro "added to cart" popup'),
            ],
        ];
    }
}
