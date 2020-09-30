<?php

namespace Mobbex\Webpay\Block;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;

/**
 * Class Info
 *
 * @package Mobbex\Webpay\Block
 */
class Info extends \Magento\Payment\Block\Info
{

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_orderFactory = $orderFactory;
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null | array $transport
     * @return DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];

        $info = $this->getInfo();
        
        $mobbexData   = $info->getAdditionalInformation("mobbex_data");
        $paymentId    = $mobbexData['payment']['id'];
        $orderUrl     = $info->getAdditionalInformation('mobbex_order_url');
        $cardInfo     = $info->getAdditionalInformation('mobbex_card_info');
        $cardPlan     = $info->getAdditionalInformation('mobbex_card_plan');
        
        if (isset($paymentId) && !empty($paymentId)) {
            $data[_("Transaction ID")] = $paymentId;
        }
        
        if (isset($orderUrl) && !empty($orderUrl)) {
            $data[_("Order URL")] = $orderUrl;
        }
        
        if (isset($cardInfo) && !empty($cardInfo)) {
            $data[_("Card Information")] = $cardInfo;
        }
        
        if (isset($cardPlan) && !empty($cardPlan)) {
            $data[_("Card Plan")] = $cardPlan;
        }
        
        if (isset($mobbexData['payment']['riskAnalysis']['level']) && !empty($mobbexData['payment']['riskAnalysis']['level'])) {
            $data[_("Risk Analysis")] = $mobbexData['payment']['riskAnalysis']['level'];
        }
        
        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
