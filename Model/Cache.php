<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class Cache
 * @package Mobbex\Webpay\Model
 */
class Cache extends AbstractModel
{
    /**
     * Initialize Model
     */
    protected function _construct()
    {
        $this->_init(\Mobbex\Webpay\Model\Source\Cache::class);
    }

    /**
     * Get data stored in mobbex chache table.
     * 
     * @param string $key Identifier key for cache data.
     * @return string|bool $data Data to store.
     */
    public function get($key)
    {
        //Get connection
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $connection    = $objectManager->get('\Magento\Framework\App\ResourceConnection')->getConnection();
        
        //Delete expired cache
        $connection->delete(
            $this->_getResource()->getMainTable(),
            ['date < ?' => new \Zend_Db_Expr('DATE_SUB(NOW(), INTERVAL 5 MINUTE)')]
        );

        $collection = $this->getCollection()
            ->addFieldToFilter('cache_key', $key)
            ->getColumnValues('data');

        return !empty($collection[0]) ? json_decode($collection[0], true) : false;
    }

    /**
     * Store data in mobbex cache table.
     * 
     * @param string $key Identifier key for data to store.
     * @param string $data Data to store.
     * @return boolean
     */
    public function store($key, $data)
    {
        //Asign data
        $this->setData('cache_key', $key);
        $this->setData('data', $data);
        $this->setData('date', date('Y-m-d H:i:s'));
        //Save data
        return $this->save();
    }
}
