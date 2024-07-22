<?php

namespace Mobbex\Webpay\Model\Source\Cache;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Cache Resource Model Collection
 * @package Mobbex\Webpay\Model\Source
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('Mobbex\Webpay\Model\Cache', 'Mobbex\Webpay\Model\Source\Cache');
    }
}