<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Swissup\FreeShippingBar\Model\BarDataProvider;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Data\BarData;

/**
 * Server side rendering for the cart page, which reloads on every qty change anyway.
 */
class Bar implements ArgumentInterface
{
    private bool $resolved = false;

    private ?BarData $barData = null;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly BarDataProvider $barDataProvider,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $placement One of Swissup\FreeShippingBar\Model\Source\Placement constants
     */
    public function isPlacementEnabled(string $placement): bool
    {
        try {
            return $this->config->isPlacementEnabled($placement, $this->storeManager->getStore()->getId());
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getBarData(): ?BarData
    {
        if ($this->resolved) {
            return $this->barData;
        }

        $this->resolved = true;

        try {
            $this->barData = $this->barDataProvider->get($this->checkoutSession->getQuote());
        } catch (\Exception $e) {
            $this->logger->error(
                'Swissup_FreeShippingBar: cart page bar failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->barData = null;
        }

        return $this->barData;
    }
}
