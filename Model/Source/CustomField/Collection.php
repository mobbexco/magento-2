<?php

namespace Mobbex\Webpay\Model\Source\CustomField;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * CustomField Resource Model Collection
 * @package Mobbex\Webpay\Model\Source
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('Mobbex\Webpay\Model\CustomField', 'Mobbex\Webpay\Model\Source\CustomField');
    }
}