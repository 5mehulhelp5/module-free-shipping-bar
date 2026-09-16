<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Html\Select;
use Swissup\FreeShippingBar\Model\Source\CustomerGroup;

/**
 * Customer group => free shipping threshold, the manual override that always beats auto-detection.
 */
class GroupThresholds extends AbstractFieldArray
{
    private ?Select $groupRenderer = null;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param CustomerGroup $customerGroupSource
     * @param array<string, mixed> $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private readonly CustomerGroup $customerGroupSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _prepareToRender(): void
    {
        $this->addColumn('customer_group', [
            'label' => __('Customer Group'),
            'renderer' => $this->getGroupRenderer(),
        ]);

        $this->addColumn('amount', [
            'label' => __('Threshold'),
            'class' => 'required-entry validate-zero-or-greater',
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Threshold');
    }

    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $group = $row->getData('customer_group');

        if ($group !== null && $group !== '') {
            $options['option_' . $this->getGroupRenderer()->calcOptionHash($group)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    private function getGroupRenderer(): Select
    {
        if ($this->groupRenderer === null) {
            /** @var Select $select */
            $select = $this->getLayout()->createBlock(Select::class);
            $this->groupRenderer = $select
                ->setIsRenderToJsTemplate(true)
                ->setOptions($this->customerGroupSource->toOptionArray())
                ->setName($this->_getCellInputElementName('customer_group'));
        }

        return $this->groupRenderer;
    }
}
