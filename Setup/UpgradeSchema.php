<?php

namespace Mobbex\Webpay\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();

        /* Add payment fee columns */

        if (version_compare($context->getVersion(), '1.2.0', '<=')) {

            $quoteAddressTable = $setup->getTable('quote_address');
            $quoteTable = $setup->getTable('quote');
            $orderTable = $setup->getTable('sales_order');
            $invoiceTable = $setup->getTable('sales_invoice');
            $creditmemoTable = $setup->getTable('sales_creditmemo');

            $feeColumn = [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
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