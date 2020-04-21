<?php
namespace Mobbex\Webpay\Block;

/**
 * Class Info
 *
 * @package Mobbex\Webpay\Block
 */
class Info extends \Magento\Payment\Block\Info
{

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_orderFactory = $orderFactory;
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null | array $transport
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $data = [];

        $info = $this->getInfo();

        $paymentData = $info->getAdditionalInformation("data");

        $paymentId = $info->getAdditionalInformation("id");
        $paymentMethod = $info->getAdditionalInformation("paymentMethod");
        $paymentStatusText = $info->getAdditionalInformation("statusText");

        if (isset($paymentId) && !empty($paymentId)) {
            $data[_("Transaction ID")] = $paymentId;
        }

        if (isset($paymentMethod) && !empty($paymentMethod)) {
            $data[_("Payment Method")] = $paymentMethod;
        }

        if (isset($paymentStatusText) && !empty($paymentStatusText)) {
            $data[_("Payment Status")] = $paymentStatusText;
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }

}
