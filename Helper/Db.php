<?php

namespace Mobbex\Webpay\Helper;

class Db extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var string */
    public $prefix;

    /** @var class */
    public $connection;

    public function __construct(\Magento\Framework\App\ResourceConnection $resource) {
        $this->connection = $resource->getConnection();
        $this->prefix     = $resource->getTableName('');
    }

    /**
     * Executes an sql script with magento 2 db connection.
     * 
     * @param string $sql An sql script to execute.
     * 
     * @return array|bool
     */
    public function query($sql)
    {
        //Get the result
        $result = $this->connection->query($sql);

        //Return the data result of the result
        if (preg_match('#^\s*\(?\s*(select|show|explain|describe|desc)\s#i', $sql))
            return $result->fetchAll();

        //Return bool if there aren't data
        return $result;
    }
}
