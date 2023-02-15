<?php

namespace Mobbex\Webpay\Helper;

class Logger extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    public $resultJsonFactory;

    /** @var \Mobbex\Webpay\Model\LogsFactory */
    public $logsFactory;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Mobbex\Webpay\Model\LogsFactory $logsFactory
    ) {
        $this->config            = $config;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->log               = $logsFactory;
    }

    /**
     * Creates a json response & log document in base of $mode
     * @param string $mode 
     * @param string $message
     * @param string $data
     * @return json
     */
    public function createJsonResponse($mode, $message, $data = [])
    {
        $this->debug($mode, $message, $data);
        return $this->resultJsonFactory->create()->setData(compact('mode', 'message', 'data'));
    }

    /**
     * Store log data in Mobbex logs table.
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function debug($mode, $message, $data = [])
    {
        //Log data
        $data = [
            'type'          => $mode,
            'message'       => $message,
            'data'          => json_encode($data),
            'date'          => date('Y-m-d H:i:s'),
        ]; 

        //Save log
        if ($mode !== 'debug' || $this->config->get('debug_mode'))
            $this->log->create()->saveLog($data);

        if($mode === 'critical')
            die($message);
    }
}