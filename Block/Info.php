<?php

namespace Mobbex\Webpay\Block;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\State;

class Info extends \Magento\Payment\Block\Info
{
    /** @var State */
    protected $_state;

    /**
     * Constructor
     *
     * @param Context $context
     * @param State $_state
     * @param Instantiator $instantiator
     * @param array $data
     */
    public function __construct(
        Context $context, 
        State $_state,
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        array $data = []
        )
    {
        parent::__construct($context, $data);
        $instantiator->setProperties($this, ['mobbexTransactionFactory', 'customFieldFactory']);
        $this->_state = $_state;
        $this->mobbexTransaction = $this->mobbexTransactionFactory->create();
        $this->customField       = $this->customFieldFactory->create();
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null|array $transport
     * 
     * @return DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);

        $mobbexData = $this->mobbexTransaction->getTransactions(['order_id' => $this->getInfo()->getOrder()->getIncrementId(), 'parent' => 1]);
        $childs     = !empty($mobbexData['childs']) ? $this->mobbexTransaction->getMobbexChilds(json_decode($mobbexData['childs'], true), $mobbexData['order_id']) : false;

        $data = [
            (string) __('Transaction ID') => isset($mobbexData['payment_id']) ? $mobbexData['payment_id'] : '',
            (string) __('Total')          => '$'.(isset($mobbexData['total']) ? $mobbexData['total'] : '0'),
            (string) __('Total Refunded') => '$'.($this->customField->getCustomField($this->getInfo()->getOrder()->getIncrementId(), 'order', 'total_refunded') ?: '0'),
        ];

        if($childs && $mobbexData['operation_type'] == 'payment.multiple-sources'){
            $data[(string) __('Payment Method')] = 'Multiple Cards';
            foreach ($childs as $key => $card){
                $data[(string) __('Card') . ' ' . ($key + 1)]                = isset($card['source_name']) ? $card['source_name'] : '';
                $data[(string) __('Card') . ' ' . ($key + 1).' Number']      = isset($card['source_number']) ? $card['source_number'] : '';
                $data[(string) __('Card') . ' ' . ($key + 1).' Installment'] = !empty($card['source_installment']) ? json_decode($card['source_installment'], true)['description'] . ' ' . json_decode($card['source_installment'], true)['count'] . ' cuota/s' : '';
                $data[(string) __('Card') . ' ' . ($key + 1).' Amount']      = '$'.(isset($card['total']) ? $card['total'] : '0');
            }
        } else {
            $data[(string) __('Payment Method')]     = isset($mobbexData['source_type']) ? $mobbexData['source_type'] : '';
            $data[(string) __('Payment Source')]     = isset($mobbexData['source_name']) ? $mobbexData['source_name'] : '';
            $data[(string) __('Source Number')]      = isset($mobbexData['source_number']) ? $mobbexData['source_number'] : '';
            $data[(string) __('Source Installment')] = !empty($mobbexData['source_installment']) ? json_decode($mobbexData['source_installment'], true)['description'] . ' ' . json_decode($mobbexData['source_installment'], true)['count'] . ' cuota/s' : '';
        }

        if($childs && $mobbexData['operation_type'] == 'payment.multiple-vendor'){
            $data[(string) __('Entites Data')] = 'Multiple Vendor';
            foreach ($childs as $key => $entity) {
                $data[(string) __('Entity') . ' ' . ($key + 1).' Name'] = isset($entity['entity_name']) ? $entity['entity_name'] : '';
                $data[(string) __('Entity') . ' ' . ($key + 1).' UID']  = isset($entity['entity_uid']) ? $entity['entity_uid'] : '';
                }
        } else {
            $data[(string) __('Entity Name')] = isset($mobbexData['entity_name']) ? $mobbexData['entity_name'] : '';
            $data[(string) __('Entity UID')]  = isset($mobbexData['entity_uid']) ? $mobbexData['entity_uid'] : '';
        }

        // Only show in admin panel
        if ($this->_state->getAreaCode() == 'adminhtml') {
            $coupon = isset($mobbexData['entity_uid']) && isset($mobbexData['payment_id']) ? "https://mobbex.com/console/" . $mobbexData['entity_uid'] . "/operations/?oid=" . $mobbexData['payment_id'] : '';

            $data = array_merge($data, [
                (string) __('Risk Analysis') => isset($mobbexData['risk_analysis']) ? $mobbexData['risk_analysis'] : '',
                (string) __('Coupon')        => isset($mobbexData['payment_id']) && isset($mobbexData['entity_uid']) ? $coupon : '',
            ]);
        }
        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
