<?php

namespace Mobbex\Webpay\Model\Source\Logs;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Mobbex Log Resource Model Collection
 * @package Mobbex\Webpay\Model\Source
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('Mobbex\Webpay\Model\Log', 'Mobbex\Webpay\Model\Source\Log');
    }
}