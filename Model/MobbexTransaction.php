<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class CustomField
 * @package Mobbex\Webpay\Model
 */
class MobbexTransaction extends AbstractModel
{
    /**
     * Initialize Model
     */
    protected function _construct()
    {
        $this->_init(\Mobbex\Webpay\Model\Resource\MobbexTransaction::class);
    }

    /**
     * Get transaction data
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * @param string $searched_column
     * 
     * @return string
     */
    public function getTransaction($order_id)
    {
      
    }

    /**
     * Saves transaction
     * 
     * @param array $data
     * 
     * @return boolean
     */
    public function saveTransaction($data)
    {
        error_log('DATA (saveTrans): ' . "\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        //Save data in mobbex transaction table
        $this->setData('order_id', $data['order_id']);
        $this->setData('parent', $data['parent']);
        $this->setData('operation_type', $data['operation_type']);
        $this->setData('payment_id', $data['payment_id']);
        $this->setData('description', $data['description']);
        $this->setData('status_code', $data['status_code']);
        $this->setData('status_message', $data['status_message']);
        $this->setData('source_name', $data['source_name']);
        $this->setData('source_type', $data['source_type']);
        $this->setData('source_reference', $data['source_reference']);
        $this->setData('source_number', $data['source_number']);
        $this->setData('source_expiration', $data['source_expiration']);
        $this->setData('source_installment', $data['source_installment']);
        $this->setData('installment_name', $data['installment_name']);
        $this->setData('installment_amount', $data['installment_amount']);
        $this->setData('installment_count', $data['installment_count']);
        $this->setData('source_url', $data['source_url']);
        $this->setData('cardholder', $data['cardholder']);
        $this->setData('entity_name', $data['entity_name']);
        $this->setData('entity_uid', $data['entity_uid']);
        $this->setData('customer', $data['customer']);
        $this->setData('checkout_uid', $data['checkout_uid']);
        $this->setData('total', $data['total']);
        $this->setData('total_webhook', $data['total_webhook']);
        $this->setData('currency', $data['currency']);
        $this->setData('risk_analysis', $data['risk_analysis']);
        $this->setData('data', $data['data']);
        $this->setData('created', $data['created']);
        $this->setData('updated', $data['updated']);

        return $this->save();
    }
}