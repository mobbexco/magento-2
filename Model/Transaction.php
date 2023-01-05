<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Transaction
 * @package Mobbex\Webpay\Model
 */
class Transaction extends AbstractModel
{
    /**
     * Initialize Model
     */
    protected function _construct()
    {
        $this->_init(\Mobbex\Webpay\Model\Resource\Transaction::class);
    }

    /**
     * Get custom transaction data from db.
     * 
     * @param array $filter  ['column_name' => 'value']
     * @return array
     */
    public function getTransactions($filter = [])
    {
        //Get the model collection
        $collection = $this->getCollection();
        //Filter the data
        foreach ($filter as $column => $value)
            $collection->addFieldToFilter($column, $value);
        //Get model data
        $data = isset($filter['parent']) && isset($collection->getData()[0]) && $filter['parent'] ? $collection->getData()[0] : $collection->getData();

        return !empty($data) ? $data : false;
    }

    /**
     * Saves webhook transaction data
     * 
     * @param array $data
     * 
     * @return boolean
     */
    public function saveTransaction($data)
    {
        //Save data in mobbex transaction table
        foreach ($data as $column => $value)
            $this->setData($column, $value);
            
        return $this->save();
    }
}