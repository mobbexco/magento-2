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
     * Return webhooks transaction data from db
     * 
     * @param int $order_id
     * @param array $parent 
     * 
     * @return array
     */
    public function getTransaction($order_id, $filter = [false])
    {
        if($filter[0]){
            $collection = $this->getCollection()
                ->addFieldToFilter('order_id', $order_id)
                ->addFieldToFilter('parent', $filter[1])
                ->getData();
            
            if($filter[1])
                $collection = isset($collection[0]) ? $collection[0] : $collection;

            return !empty($collection) ? $collection : false;
        }
        $collection = $this->getCollection()
            ->addFieldToFilter('order_id', $order_id)
            ->getData();
            
        return !empty($collection) ? $collection : false;
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
        $this->setData('currency', $data['currency']);
        $this->setData('risk_analysis', $data['risk_analysis']);
        $this->setData('data', $data['data']);
        $this->setData('created', $data['created']);
        $this->setData('updated', $data['updated']);

        return $this->save();
    }
}