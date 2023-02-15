<?php

namespace Mobbex\Webpay\Model\Resource;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Mobbex Log Resource Model
 * @package Mobbex\Webpay\Model\Resource
 */
class Log extends AbstractDb
{
    /**
     * Initialize resource
     */
    public function _construct()
    {
        $this->_init('mobbex_log', 'log_id');
    }
}