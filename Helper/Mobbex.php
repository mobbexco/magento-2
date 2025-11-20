<?php

namespace Mobbex\Webpay\Helper;

class Mobbex
{
    /** @var \Mobbex\Webpay\Helper\Logger */
    private $logger;

    /** @var \Mobbex\Webpay\Helper\Order */
    private $orderHelper;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    private $cf;

    /** @var \Magento\Checkout\Model\Session */
    private $checkoutSession;

    public function __construct(
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Order $orderHelper,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->logger             = $logger;
        $this->orderHelper        = $orderHelper;
        $this->cf                 = $customFieldFactory;
        $this->checkoutSession    = $checkoutSession;
    }

    /**
     * Create a checkout for the current order.
     * 
     * @return array
     */
    public function createPaymentIntent()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Build intent using order data (same as checkout)
        $checkoutData = $this->orderHelper->buildCheckoutData($order);
        $intent = new \Mobbex\Modules\Checkout(
            $checkoutData['id'],
            $checkoutData['total'],
            $checkoutData['return_url'],
            $checkoutData['webhook'],
            $checkoutData['currency'],
            $checkoutData['items'],
            $checkoutData['installments'],
            $checkoutData['customer'],
            $checkoutData['addresses'],
            'all',
            'mobbexCheckoutRequest',
            $checkoutData['description'],
            $checkoutData['reference']
        );

        $this->logger->log(
            'debug',
            "Mobbex\Webpay\Helper\Checkout::createPaymentIntent | Checkout Payment Intent Response: ",
            $intent->response
        );

        // Save checkout uid to use later in payment failed page
        if (isset($intent->response['id']))
            $this->cf->create()->saveCustomField(
                $order->getId(),
                'order',
                'checkout_uid',
                $intent->response['id']
            );

        return $intent->response;
    }
}
