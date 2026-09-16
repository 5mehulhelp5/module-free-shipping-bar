<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\CustomerData;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Swissup\FreeShippingBar\Model\BarDataProvider;
use Swissup\FreeShippingBar\Model\Config;
use Swissup\FreeShippingBar\Model\Source\Placement;

/**
 * Private customer data behind the minicart bar. Invalidated by etc/frontend/sections.xml.
 */
class FreeShippingBar implements SectionSourceInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly BarDataProvider $barDataProvider,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSectionData(): array
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();

            if (!$this->config->isPlacementEnabled(Placement::MINICART, $storeId)) {
                return ['show' => false];
            }

            $data = $this->barDataProvider->get($this->checkoutSession->getQuote());
        } catch (\Exception $e) {
            // Section sources run on nearly every page; never let one break the minicart.
            $this->logger->error(
                'Swissup_FreeShippingBar: minicart section failed: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return ['show' => false];
        }

        if ($data === null) {
            return ['show' => false];
        }

        return ['show' => true] + $data->toArray();
    }
}
