<?php

namespace Mobbex\Webpay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();

        /* Add mobbex transaction table */
        if (!$setup->tableExists('mobbex_transaction')) {
            $table = $connection
            ->newTable($setup->getTable('mobbex_transaction'))
            ->addColumn('id', Table::TYPE_INTEGER, null, array(
                'identity'  => true,
                'unsigned'  => true,
                'nullable'  => false,
                'primary'   => true,
                ), 'Id')
            ->addColumn('order_id', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Order id')
            ->addColumn('parent', Table::TYPE_BOOLEAN, null, array(
                'nullable'  => false,
                ), 'Parent')
            ->addColumn('operation_type', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Operation type')
            ->addColumn('payment_id', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Payment id')
            ->addColumn('description', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Description')
            ->addColumn('status_code', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Status code')
            ->addColumn('status_message', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Status message')
            ->addColumn('source_name', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Source name')
            ->addColumn('source_type', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Source type')
            ->addColumn('source_reference', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Source reference')
            ->addColumn('source_number', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Source number')
            ->addColumn('source_expiration', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'source expiration')
            ->addColumn('source_installment', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Source installment')
            ->addColumn('installment_name', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Installment name')
            ->addColumn('installment_amount', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Installment amount')
            ->addColumn('installment_count', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Installment count')
            ->addColumn('source_url', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Source url')
            ->addColumn('cardholder', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Cardholder')
            ->addColumn('entity_name', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Entity name')
            ->addColumn('entity_uid', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Entity uid')
            ->addColumn('customer', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Customer')
            ->addColumn('checkout_uid', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Checkout uid')
            ->addColumn('total', Table::TYPE_DECIMAL, '18,2', array(
                'nullable'  => false,
                ), 'Total')
            ->addColumn('currency', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Currency')
            ->addColumn('risk_analysis', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
                ), 'Risk analysis')
            ->addColumn('data', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
            ), 'Data')
            ->addColumn('created', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
            ), 'Created')
            ->addColumn('updated', Table::TYPE_TEXT, null, array(
                'nullable'  => false,
            ), 'Updated');
        
            $connection->createTable($table);
        }

        /* Add mobbex custom field table */
        if (!$setup->tableExists('mobbex_customfield')) {
            $table = $connection->newTable($setup->getTable('mobbex_customfield'))
                ->addColumn('customfield_id', Table::TYPE_INTEGER, null, array(
                    'identity'  => true,
                    'unsigned'  => true,
                    'nullable'  => false,
                    'primary'   => true,
                    ), 'Id')
                ->addColumn('row_id', Table::TYPE_INTEGER, null, array(
                    'nullable'  => false,
                    ), 'Row id')
                ->addColumn('object', Table::TYPE_TEXT, null, array(
                    'nullable'  => false,
                    ), 'Object')
                ->addColumn('field_name', Table::TYPE_TEXT, null, array(
                    'nullable'  => false,
                    ), 'Field name')
                ->addColumn('data', Table::TYPE_TEXT, null, array(
                    'nullable'  => false,
                ),
            'Data');
        
            $connection->createTable($table);
        }

        /* Add mobbex logs table */
        if (!$setup->tableExists('mobbex_logs')) {
            $table = $connection->newTable($setup->getTable('mobbex_logs'))
                ->addColumn('log_id', Table::TYPE_INTEGER, null, array(
                    'identity'  => true,
                    'unsigned'  => true,
                    'nullable'  => false,
                    'primary'   => true,
                ), 'Id')
                ->addColumn('type', Table::TYPE_TEXT, null, array(
                    'nullable'  => false,
                ), 'Type')
                ->addColumn('message', Table::TYPE_TEXT, null, array(
                    'nullable'  => false,
                ), 'Message')
                ->addColumn(
                    'data',
                    Table::TYPE_TEXT, null, array(
                    'nullable'  => false,
                ), 'Data')
                ->addColumn(
                    'date',
                    Table::TYPE_DATETIME, null, array(
                    'nullable'  => false,
                ), 'Creation Date');

            $connection->createTable($table);
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
