<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Source;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Every customer group including NOT LOGGED IN, and without the "ALL GROUPS" pseudo-group that
 * Magento\Customer\Model\Customer\Source\Group prepends — the override table matches one exact
 * group id per row, so an "all" entry would have no meaning here.
 */
class CustomerGroup implements OptionSourceInterface
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $options = null;

    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->options = [['value' => '', 'label' => __('-- Please Select --')]];

        try {
            $groups = $this->groupRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        } catch (\Exception $e) {
            return $this->options;
        }

        foreach ($groups as $group) {
            $this->options[] = [
                'value' => (string) $group->getId(),
                'label' => sprintf('%s (ID %s)', $group->getCode(), $group->getId()),
            ];
        }

        return $this->options;
    }
}
