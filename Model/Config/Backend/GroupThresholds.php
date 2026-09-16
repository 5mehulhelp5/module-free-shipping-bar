<?php

declare(strict_types=1);

namespace Swissup\FreeShippingBar\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Stores the dynamic-rows override table as JSON, keeping one row per customer group.
 */
class GroupThresholds extends Value
{
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly Json $json,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (!is_array($value)) {
            $this->setValue($value === null || $value === '' ? '' : (string) $value);

            return parent::beforeSave();
        }

        unset($value['__empty']);

        $rows = [];
        $seen = [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $group = $row['customer_group'] ?? '';
            $amount = $row['amount'] ?? '';

            if ($group === '' || $amount === '') {
                continue;
            }

            if (!is_numeric($amount) || (float) $amount < 0) {
                throw new LocalizedException(
                    __('Free Shipping Bar: threshold "%1" must be a number of 0 or more.', $amount)
                );
            }

            $groupId = (int) $group;

            if (isset($seen[$groupId])) {
                throw new LocalizedException(
                    __('Free Shipping Bar: customer group ID %1 is listed more than once.', $groupId)
                );
            }

            $seen[$groupId] = true;

            $rows[] = [
                'customer_group' => (string) $groupId,
                'amount' => (string) (float) $amount,
            ];
        }

        $this->setValue($rows ? $this->json->serialize($rows) : '');

        return parent::beforeSave();
    }

    protected function _afterLoad()
    {
        $value = $this->getValue();

        if (!is_string($value) || $value === '') {
            $this->setValue([]);

            return $this;
        }

        try {
            $rows = $this->json->unserialize($value);
        } catch (\InvalidArgumentException $e) {
            $rows = [];
        }

        $result = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (is_array($row)) {
                $result['_' . $index] = $row;
            }
        }

        $this->setValue($result);

        return $this;
    }
}
