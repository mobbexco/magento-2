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

    /** @var \Magento\Framework\Message\ManagerInterface */ 
    public $messageManager;

    public $modes = [
        'error' => 'error',
        'debug' => 'debug',
        'fatal' => 'crit',
    ];

    public function __construct(
        \Zend\Log\Logger $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->logger            = $logger;
        $this->config            = $config;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->messageManager    = $messageManager;
    }

    /**
     * Creates a json response & logs the data.
     * @param string $mode 
     * @param string $message
     * @param string $data
     * @return json
     */
    public function createJsonResponse($mode, $message, $data = [])
    {
        $this->log($mode, $message, $data);
        return $this->resultJsonFactory->create()->setData(compact('mode', 'message', 'data'));
    }

    /**
     * Creates log document to log errors & useful data.
     * 
     * Mode debug: Log data only if debug mode is active
     * Mode error: Always log data.
     * Mode critical: Always log data & stop code execution.
     * 
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function log($mode, $message, $data = [])
    {
        //set mode
        $method = $this->modes[$mode];
        //Log data
        $writer = new Stream(BP . '/var/log/' . "mobbex_$mode" . "_" . date('m_Y') . ".log");
        $this->logger->addWriter($writer);

        if ($mode !== 'debug' || $this->config->get('debug_mode'))
            $this->logger->{$method}($message, $data);

        if($mode === 'critical')
            die($message);
    }
}