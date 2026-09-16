<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Swissup\FreeShippingBar\Model\Config;

/**
 * Admin-configurable colours reach the stylesheet as CSS custom properties rendered into the
 * page, rather than as inline styles on every bar — one FPC-cached declaration per store, and
 * the Less keeps full control over everything else.
 */
class Styles implements ArgumentInterface
{
    private const DEFAULTS = [
        'track' => '#e8e8e8',
        'fill' => '#1979c3',
        'success' => '#006400',
    ];

    /**
     * Anything an admin can type ends up inside a stylesheet, so only plain colour literals pass.
     */
    private const COLOR_PATTERN = '/^(#[0-9a-f]{3,8}|rgba?\([0-9,.\s%]+\)|hsla?\([0-9,.\s%deg]+\)|[a-z]{3,20})$/i';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isEnabled(): bool
    {
        try {
            return $this->config->isEnabled($this->storeManager->getStore()->getId())
                && $this->config->getPlacements($this->storeManager->getStore()->getId()) !== [];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getColors(): array
    {
        try {
            $configured = $this->config->getColors($this->storeManager->getStore()->getId());
        } catch (\Exception $e) {
            $configured = [];
        }

        $colors = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = trim((string) ($configured[$key] ?? ''));
            $colors[$key] = $value !== '' && preg_match(self::COLOR_PATTERN, $value) ? $value : $default;
        }

        return $colors;
    }
}
