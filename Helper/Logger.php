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

    /** @var \Psr\Log\LoggerInterface */
    public $fileLogger;

    /** @var bool */
    public $useFileLogger = false;

    /** @var bool */
    public $debugMode = false;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Mobbex\Webpay\Model\LogFactory $logFactory,
        \Psr\Log\LoggerInterface $fileLogger
        
    ) {
        $this->config            = $config;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logFactory        = $logFactory;
        $this->fileLogger        = $fileLogger;

        // Set here to reduce db requests
        $this->debugMode = $this->config->get('debug_mode');
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

        //Create json response.
        $resultJson = $this->resultJsonFactory->create()->setData(compact('mode', 'message', 'data'));
        $resultJson->setData(compact('mode', 'message', 'data'));
        $resultJson->setHttpResponseCode($mode === 'debug' ? 200 : 400);
        
        return $resultJson;
    }

    /**
     * Store log data in Mobbex logs table.
     * 
     * Mode debug: Log data only if debug mode is active
     * Mode error: Always log data.
     * 
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function log($mode, $message, $data = [])
    {
        $filteredData = $this->hideSensibleData($data);

        // Save log to db
        if ($mode != 'debug' || $this->debugMode)
            $this->useFileLogger
                ? $this->fileLogger->$mode($message, $filteredData)
                : $this->logFactory->create()->saveLog([
                    'type'          => $mode,
                    'message'       => $message,
                    'data'          => json_encode($filteredData),
                    'date'          => date('Y-m-d H:i:s'),
                ]);
    }

    /**
     * Hide sensible data from log.
     * 
     * @param array $data
     */
    private function hideSensibleData($data)
    {
        if (isset($data['body']['source']['card']['number']))
            $data['body']['source']['card']['number'] = substr($data['body']['source']['card']['number'], 0, 6) . str_repeat('X', strlen($data['body']['source']['card']['number']) - 10) . substr($data['body']['source']['card']['number'], -4);

        if (isset($data['body']['source']['card']['cvv']))
            $data['body']['source']['card']['cvv'] = '[REDACTED]';

        return $data;
    }
}