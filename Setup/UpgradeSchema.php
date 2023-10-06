<?php

namespace Mobbex\Webpay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public $tables = [
        'transaction',
        'cache',
        'custom_fields',
        'log',
    ];

    public function __construct(
        \Mobbex\Webpay\Helper\Db $db
    ) {
        // Do not load SDK using helper (fix for Magento 2.4.3)
        \Mobbex\Platform::loadModels(null, $db);
    }

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        //Init connection
        $setup->startSetup();
        $connection = $setup->getConnection();

        //Change old table names        
        //logs
        if ($setup->tableExists('mobbex_logs'))
            $setup->run('ALTER TABLE ' . $setup->getTable('mobbex_logs') . ' RENAME TO ' . $setup->getTable('mobbex_log') . ';');

        foreach ($this->tables as $tableName) {
            //Get definition
            $definition = \Mobbex\Model\Table::getTableDefinition($tableName);

            //Modify customfield definition for compatibility
            if ($tableName === 'custom_fields') {
                //Change table name
                $tableName = 'customfield';
                
                //adapt definition to magento 2 customfield definition
                foreach ($definition as &$column)
                    if ($column['Field'] === 'id')
                        $column['Field'] = 'customfield_id';
            }

            //Create the table
            $table = new \Mobbex\Model\Table($tableName, $definition);
        }
        
        /* Add payment fee columns */

        if (version_compare($context->getVersion(), '1.2.0', '<=')) {
            $quoteAddressTable = $setup->getTable('quote_address');
            $quoteTable = $setup->getTable('quote');
            $orderTable = $setup->getTable('sales_order');
            $invoiceTable = $setup->getTable('sales_invoice');
            $creditmemoTable = $setup->getTable('sales_creditmemo');

            $feeColumn = [
                'type' => Table::TYPE_DECIMAL,
                'length' =>'10,2',
                'default' => 0.00,
                'nullable' => true,
                'comment' =>'Fee'
            ];

            $connection->addColumn($quoteAddressTable, 'fee', $feeColumn);
            $connection->addColumn($quoteTable, 'fee', $feeColumn);
            $connection->addColumn($orderTable, 'fee', $feeColumn);
            $connection->addColumn($invoiceTable, 'fee', $feeColumn);
            $connection->addColumn($creditmemoTable, 'fee', $feeColumn);
        }

        $setup->endSetup();
    }
}
