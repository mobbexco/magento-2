<?php

namespace Mobbex\Webpay\Block;

class PaymentFailure extends \Magento\Backend\Block\Template
{
    /** @var string */
    public $method;

    /** @var string */
    public $checkoutUid;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Backend\Block\Template\Context $context,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $order = $checkoutSession->getLastRealOrder();

        // Check if there is a valid order
        if (!$order || !$order->getId())
            return;

        $payment = $order->getPayment();

        // Check if payment method is Mobbex
        if (!$payment || $payment->getMethod() != 'sugapay')
            return;

        $this->method = $payment->getMethod();
        $this->checkoutUid = $customFieldFactory->create()->getCustomField(
            $order->getId(),
            'order',
            'checkout_uid'
        );
    }
}