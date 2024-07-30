<?php

namespace Mobbex\Webpay\Model\Source;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Mobbex Transaction Resource Model
 * @package Mobbex\Webpay\Model\Source
 */
class Transaction extends AbstractDb
{
    /**
     * Initialize resource
     */
    public function _construct()
    {
        $this->_init('mobbex_transaction', 'id');
    }
}