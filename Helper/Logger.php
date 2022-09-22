<?php

namespace Mobbex\Webpay\Helper;

use Zend\Log\Writer\Stream;

class Logger extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var Zend\Log\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    public $resultJsonFactory;

    public function __construct(
        \Zend\Log\Logger $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->logger            = $logger;
        $this->config            = $config;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Creates a json response & log document in base of $mode.
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function createJsonResponse($mode, $message, $data = [])
    {
        //Log data
        $writer = new Stream(BP . '/var/log/' . "mobbex_$mode". "_" . date('m_Y') . ".log");
        $this->logger->addWriter($writer);
        if($mode !== 'debug' || $this->config->get('debug_mode'))
            $this->logger->{$mode}($message, $data);

        return $this->resultJsonFactory->create()->setData(compact('mode', 'message', 'data'));
    }    
}