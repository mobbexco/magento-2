<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CancelOrderObserver implements ObserverInterface
{
    public function __construct(\Magento\Framework\App\Action\Context $context, \Mobbex\Webpay\Helper\Instantiator $instantiator)
    {
        $instantiator->setProperties($this, ['customFieldFactory', 'orderUpdate', '_order']);
        $this->params       = $context->getRequest()->getParams();
        $this->customFields = $this->customFieldFactory->create();
    }

    /**
     * Executes after order is cancelled.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        //get order
        if(isset($this->params['form_key']) && isset($this->params['order_id'])){
            $this->updateStock($this->_order->load($this->params['order_id']));
        } else if(isset($this->params["selected"])){
            foreach ($this->params['selected'] as $orderId)
                $this->updateStock($this->_order->load($orderId));
        } else if(isset($this->params['order_id'])){
            $this->updateStock($this->_order->loadByIncrementId($this->params['order_id']));    
        }
    }

    /**
     *  Avoid to refund stock if order was refunded previusly
     *  @param Order
     */
    public function updateStock($order)
    {
        //Check if order was refunded
        $refunded = $this->customFields->getCustomField($order->getIncrementId(), 'order', 'refunded') === 'yes' ? true : false;

        //If order was refunded discount stock to avoid duplicate refund
        if ($refunded)
            $this->orderUpdate->updateStock($order, false);

        //Set order as refunded
        return $this->customFields->saveCustomField($order->getIncrementId(), 'order', 'refunded', 'yes');
    }
}
