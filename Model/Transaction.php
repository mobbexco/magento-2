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

    /**
     * Get data from childs node.
     * 
     * @param array $childs
     * @param string $orderId
     * 
     * @return array
     */
    public function getMobbexChilds($childs, $orderId)
    {
        $childsData = [];
        foreach ($childs as $child)
            $childsData[] = $this->formatWebhookData($child, $orderId);

        return $childsData;
    }

    /**
     * Check if webhook is parent type using him payment id.
     * 
     * @param string $paymentId
     * 
     * @return bool
     */
    public function isParent($paymentId)
    {
        return strpos($paymentId, 'CHD-') !== 0;
    }

    /**
     * Format the webhook data to save in db.
     * 
     * @param array $webhookData
     * @param int $orderId
     * 
     * @return array
     */
    public function formatWebhookData($webhookData, $orderId)
    {
        $data = [
            'order_id'           => $orderId,
            'parent'             => isset($webhookData['payment']['id']) ? $this->isParent($webhookData['payment']['id']) : false,
            'childs'             => isset($webhookData['childs']) ? json_encode($webhookData['childs']) : '',
            'operation_type'     => isset($webhookData['payment']['operation']['type']) ? $webhookData['payment']['operation']['type'] : '',
            'payment_id'         => isset($webhookData['payment']['id']) ? $webhookData['payment']['id'] : '',
            'description'        => isset($webhookData['payment']['description']) ? $webhookData['payment']['description'] : '',
            'status_code'        => isset($webhookData['payment']['status']['code']) ? $webhookData['payment']['status']['code'] : '',
            'status_message'     => isset($webhookData['payment']['status']['message']) ? $webhookData['payment']['status']['message'] : '',
            'source_name'        => isset($webhookData['payment']['source']['name']) ? $webhookData['payment']['source']['name'] : 'Mobbex',
            'source_type'        => isset($webhookData['payment']['source']['type']) ? $webhookData['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($webhookData['payment']['source']['reference']) ? $webhookData['payment']['source']['reference'] : '',
            'source_number'      => isset($webhookData['payment']['source']['number']) ? $webhookData['payment']['source']['number'] : '',
            'source_expiration'  => isset($webhookData['payment']['source']['expiration']) ? json_encode($webhookData['payment']['source']['expiration']) : '',
            'source_installment' => isset($webhookData['payment']['source']['installment']) ? json_encode($webhookData['payment']['source']['installment']) : '',
            'installment_name'   => isset($webhookData['payment']['source']['installment']['description']) ? json_encode($webhookData['payment']['source']['installment']['description']) : '',
            'installment_amount' => isset($webhookData['payment']['source']['installment']['amount']) ? $webhookData['payment']['source']['installment']['amount'] : '',
            'installment_count'  => isset($webhookData['payment']['source']['installment']['count']) ? $webhookData['payment']['source']['installment']['count'] : '',
            'source_url'         => isset($webhookData['payment']['source']['url']) ? json_encode($webhookData['payment']['source']['url']) : '',
            'cardholder'         => isset($webhookData['payment']['source']['cardholder']) ? json_encode(($webhookData['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($webhookData['entity']['name']) ? $webhookData['entity']['name'] : '',
            'entity_uid'         => isset($webhookData['entity']['uid']) ? $webhookData['entity']['uid'] : '',
            'customer'           => isset($webhookData['customer']) ? json_encode($webhookData['customer']) : '',
            'checkout_uid'       => isset($webhookData['checkout']['uid']) ? $webhookData['checkout']['uid'] : '',
            'total'              => isset($webhookData['payment']['total']) ? $webhookData['payment']['total'] : '',
            'currency'           => isset($webhookData['checkout']['currency']) ? $webhookData['checkout']['currency'] : '',
            'risk_analysis'      => isset($webhookData['payment']['riskAnalysis']['level']) ? $webhookData['payment']['riskAnalysis']['level'] : '',
            'data'               => isset($webhookData) ? json_encode($webhookData) : '',
            'created'            => isset($webhookData['payment']['created']) ? $webhookData['payment']['created'] : '',
            'updated'            => isset($webhookData['payment']['updated']) ? $webhookData['payment']['created'] : '',
        ];

        return $data;
    }
}