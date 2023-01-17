<?php

namespace Mobbex\Webpay\Model\Resource;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Mobbex Logs Resource Model
 * @package Mobbex\Webpay\Model\Resource
 */
class Logs extends AbstractDb
{
    /**
     * Initialize resource
     */
    public function _construct()
    {
        $this->_init('mobbex_logs', 'log_id');
    }
}