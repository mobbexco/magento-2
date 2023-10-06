<?php

namespace Mobbex\Webpay\Controller\AdminHtml\Payment;

class Capture extends \Magento\Backend\App\Action
{
    /** @var \Magento\Sales\Model\Order */
    public $_order;

    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Model\TransactionFactory */
    public $mobbexTransactionFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Sales\Model\Order $order,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Model\TransactionFactory $mobbexTransactionFactory
    ) {
        parent::__construct($context);

        $this->sdk                      = $sdk;
        $this->_order                   = $order;
        $this->mobbexTransactionFactory = $mobbexTransactionFactory;
        $this->logger                   = $logger;

        //Init mobbex php plugins sdk
        $this->sdk->init();
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
            $this->logger->log('error', 'Capture > execute | '.$e->getMessage(), isset($e->data) ? $e->data : []);
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $id]);
    }
}