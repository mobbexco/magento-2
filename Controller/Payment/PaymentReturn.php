<?php

namespace Mobbex\Webpay\Controller\Payment;

use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Class PaymentReturn
 * @package Mobbex\Webpay\Controller\Payment
 */
class PaymentReturn implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Instantiator */
    public $instantiator;

    /** @var Magento\Sales\Model\Service\InvoiceService */
    public $_invoiceService;

    /** @var Magento\Framework\DB\Transaction */
    protected $_transaction;

    /** @var \Magento\Framework\Message\ManagerInterface */
    protected $messageManager;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Framework\DB\Transaction $_transaction,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $instantiator->setProperties($this, ['sdk', 'config', 'helper', 'logger', 'quoteFactory','redirectFactory', '_request', '_cart', '_checkoutSession', '_order']);
        $this->_invoiceService = $_invoiceService;
        $this->_transaction    = $_transaction;
        $this->_messageManager = $messageManager;
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     * @throws Exception
     */
    public function execute()
    {
        try {
            // Get data
            extract($this->_request->getParams());

            $this->logger->createJsonResponse('debug', 'PaymentReturn Controller > Request', ["id" => $order_id, "status" => !empty($status) ? $status : '']);

            if (empty($order_id) && !empty($quote_id)) {
                $quote = $this->quoteFactory->create()->load($quote_id);
                $order_id = $quote->getReservedOrderId();
            }

            // if data looks fine
            if (isset($order_id)) {
                // Get Order
                $order = $this->_order->loadByIncrementId($order_id);

                $this->logger->createJsonResponse('debug', 'PaymentReturn Controller > Order', $this->_order->debug());

                // Cancel order to return stock when payment is not attempted
                if (empty($status)) {
                    $order->cancel();
                    $order->save();
                }
                if ($status > 1 && $status < 400) {
                    return $this->redirectFactory->create()->setPath('checkout/onepage/success');
                } else {
                    $this->restoreCart($order);
                    return $this->redirectFactory->create()->setPath('checkout/');
                }


            } else {
                $this->_messageManager->addError(__("Invalid order number"));
                $this->logger->createJsonResponse('err', 'Payment Return called without order id');
                return $this->redirectFactory->create()->setPath('home');
            }

        } catch (Exception $e) {
            return $this->logger->createJsonResponse('err', 'PaymentReturn Controller > Error: ' . $e->getMessage());
        }
    }

    /**
     * @param $order
     */
    private function restoreCart($order)
    {
        //Get Quote
        $quote = $this->quoteFactory->create()->load($order->getQuoteId());
        //Debug data
        $this->logger->createJsonResponse('debug', 'Return Controller > Quote', $quote->debug());
        //Restore cart
        $quote->setReservedOrderId(null);
        $quote->setIsActive(true);
        $quote->removePayment();
        $quote->save();

        $this->_checkoutSession->replaceQuote($quote);
        $this->_cart->setQuote($quote);
        $this->_checkoutSession->restoreQuote();
    }
}
