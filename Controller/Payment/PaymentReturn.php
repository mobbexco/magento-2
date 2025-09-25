<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class PaymentReturn. 
 * 
 * Called after a payment is completed (success or failure) in Mobbex.
 *
 * @package Mobbex\Webpay\Controller\Payment
 */
class PaymentReturn extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Logger */
    protected $logger;

    /** @var \Mobbex\Webpay\Helper\Config */
    protected $config;

    /** @var \Magento\Framework\App\RequestInterface */
    protected $request;

    /** @var \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    /** @var \Magento\Framework\Message\ManagerInterface */
    protected $messageManager;

    public function __construct(
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        parent::__construct($context);

        $this->config          = $config;
        $this->logger          = $logger;
        $this->request         = $request;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager  = $messageManager;
    }

    public function execute()
    {
        try {
            $this->logger->log('debug', 'PaymentReturn::execute', ["params" => $this->request->getParams()]);

            $status = $this->request->getParam('status');
    
            // If the payment is not successful, show an error and redirect to checkout
            if ($status === 'undefined' || !$status || $status <= 1 || $status >= 400) {
                if ($this->config->get('restore_cart')) {
                    $this->checkoutSession->restoreQuote();

                    // Maybe redirect to checkout payment step
                    return $this->_redirect('checkout', ['_fragment' => 'payment']);
                }

                return $this->_redirect('checkout/onepage/failure');
            }

            return $this->_redirect('checkout/onepage/success');
        } catch (\Exception $e) {
            $this->logger->log('error', 'PaymentReturn::execute ERROR: ' . $e->getMessage(), $this->request->getParams());

            $this->messageManager->addError(
                __("Ha ocurrido un error al redireccionar. Por favor, comuníquese con el administrador de la tienda.")
            );

            return $this->_redirect('checkout', ['_fragment' => 'payment']);
        }
    }
}
