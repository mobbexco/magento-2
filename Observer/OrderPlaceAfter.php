<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderPlaceAfter implements ObserverInterface
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Config $config
    )
    {
        $this->config       = $config;
    }

    /**
     * Executes after order is placed.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        $order = $observer->getEvent()->getOrder();

        // Return if order wasn't placed with mobbex
        if($order->getPayment()->getMethod() != 'sugapay')
            return;
        
        // Get status configured to order created
        $orderStatus = $this->config->get('order_status_in_process');
        
        // Asign status to order
        $order->setState($orderStatus)->setStatus($orderStatus);
    }
}
