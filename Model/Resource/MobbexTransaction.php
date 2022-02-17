<?php

namespace Mobbex\Webpay\Model\Resource;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Mobbex Transaction Resource Model
 * @package Mobbex\Webpay\Model\Resource
 */
class MobbexTransaction extends AbstractDb
{
    /**
     * Initialize resource
     */
    public function _construct()
    {
        $this->_init('mobbex_transaction', 'id');
    }
}