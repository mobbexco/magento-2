<?php

namespace Mobbex\Webpay\Controller\AdminHtml\Payment;

class Capture extends \Magento\Backend\App\Action
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Instantiator $instantiator
    ) {
        parent::__construct($context);
        $instantiator->setProperties($this, ['sdk', 'logger', 'mobbexTransactionFactory', '_order']);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Get order with him id
            $id    = $this->getRequest()->getParam('order_id');
            $order = $this->_order->load($id);

            // Get transaction data from db
            $transaction = $this->mobbexTransactionFactory->create()->getTransactions(['order_id' => $order->getIncrementId(), 'parent' => 1]);

            // Make capture request
            $result = \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => "operations/$transaction[payment_id]/capture",
                'body'   => ['total' => $order->getGrandTotal()],
            ]);

            if (!$result)
                throw new \Exception('Uncaught Exception on Mobbex Request', 500);

            $this->messageManager->addSuccessMessage('Operación capturada correctamente');
        } catch (\Exception $e) {
            // Add message to admin panel and debug
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug('error', 'Capture > execute | '.$e->getMessage(), isset($e->data) ? $e->data : []);
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $id]);
    }
}