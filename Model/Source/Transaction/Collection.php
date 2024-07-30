<?php

namespace Mobbex\Webpay\Model\Source\Transaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Mobbex Transaction Resource Model Collection
 * @package Mobbex\Webpay\Model\Source
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('Mobbex\Webpay\Model\Transaction', 'Mobbex\Webpay\Model\Source\Transaction');
    }
}