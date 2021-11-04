<?php

namespace Mobbex\Webpay\Model\Resource\MobbexTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Transaction Resource Model Collection
 * @package Mobbex\Webpay\Model\Resource
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('Mobbex\Webpay\Model\MobbexTransaction', 'Mobbex\Webpay\Model\Resource\MobbexTransaction');
    }
}