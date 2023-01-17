<?php

namespace Mobbex\Webpay\Model\Resource\Logs;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Mobbex Logs Resource Model Collection
 * @package Mobbex\Webpay\Model\Resource
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('Mobbex\Webpay\Model\Logs', 'Mobbex\Webpay\Model\Resource\Logs');
    }
}