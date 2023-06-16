<?php

namespace Mobbex\Webpay\Model\Resource;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Cache Resource Model
 * @package Mobbex\Webpay\Model\Resource
 */
class Cache extends AbstractDb
{
    protected $_isPkAutoIncrement = false;
    
    /**
     * Initialize resource
     */
    public function _construct()
    {
        $this->_init('mobbex_cache', 'cache_key');
    }
}