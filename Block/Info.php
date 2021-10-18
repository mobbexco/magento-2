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
     * @param State $state
     * @param array $data
     */
    public function __construct(Context $context, State $state, array $data = [])
    {
        parent::__construct($context, $data);
        $this->_state = $state;
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

        $info       = $this->getInfo();
        $mobbexData = $info->getAdditionalInformation('mobbex_data');

        $data = [
            (string) __('Transaction ID')   => isset($mobbexData['payment']['id']) ? $mobbexData['payment']['id'] : '',
            (string) __('Payment Method')   => $info->getAdditionalInformation('mobbex_payment_method') ?: '',
            (string) __('Card Information') => $info->getAdditionalInformation('mobbex_card_info') ?: '',
            (string) __('Card Plan')        => $info->getAdditionalInformation('mobbex_card_plan') ?: '',
        ];

        // Only show in admin panel
        if ($this->_state->getAreaCode() == 'adminhtml') {
            $data = array_merge($data, [
                (string) __('Cupon URL')     => $info->getAdditionalInformation('mobbex_order_url') ?: '',
                (string) __('Risk Analysis') => isset($mobbexData['payment']['riskAnalysis']['level']) ? $mobbexData['payment']['riskAnalysis']['level'] : '',
            ]);
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}