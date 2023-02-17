<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Log
 * @package Mobbex\Webpay\Model
 */
class Log extends AbstractModel
{
    /**
     * Initialize Model
     */
    protected function _construct()
    {
        $this->_init(\Mobbex\Webpay\Model\Resource\Log::class);
    }

    /**
     * Get log data.
     * 
     * @param array $filter  ['column_name' => 'value']
     * @return array
     */
    public function getLog($filter = [])
    {
        //Get the model collection
        $collection = $this->getCollection();
        //Filter the data
        foreach ($filter as $column => $value)
            $collection->addFieldToFilter($column, $value);

        return !empty($data) ? $data : false;
    }

    /**
     * Saves log data in DB.
     * 
     * @param array $data
     * 
     * @return boolean
     */
    public function saveLog($data)
    {
        //Save data in mobbex transaction table
        foreach ($data as $column => $value)
            $this->setData($column, $value);
            
        return $this->save();
    }
}