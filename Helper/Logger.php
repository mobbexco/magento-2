<?php

namespace Mobbex\Webpay\Helper;

class Logger extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    public $resultJsonFactory;

    /** @var \Mobbex\Webpay\Model\LogFactory */
    public $logFactory;

    /**
     * Constructor.
     * 
     * @param \Mobbex\Webpay\Helper\Config $config
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Mobbex\Webpay\Model\LogFactory $logFactory
     * 
     */
    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Mobbex\Webpay\Model\LogFactory $logFactory
    ) {
        $this->config            = $config;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logFactory        = $logFactory;
    }

    /**
     * Creates a json response & logs the data.
     *
     * Creates a json response & log document in base of $mode
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
     * Store log data in Mobbex logs table.
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
        // Save log to db
        if ($mode != 'debug' || $this->config->get('debug_mode'))
            $this->logFactory->create()->saveLog([
                'type'          => $mode,
                'message'       => $message,
                'data'          => json_encode($data),
                'date'          => date('Y-m-d H:i:s'),
            ]);

        if($mode === 'critical')
            die($message);
    }
}