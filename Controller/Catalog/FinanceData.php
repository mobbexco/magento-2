<?php

namespace Mobbex\Webpay\Controller\Catalog;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class FinanceData extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var \Mobbex\Webpay\Helper\Config */
    protected $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result       = $this->resultJsonFactory->create();
        $productIds   = $this->getRequest()->getParam('product_ids', []);
        $productsData = [];

        if (empty($productIds) || !is_array($productIds))
            return $result->setData(['error' => 'No product IDs provided.']);

        foreach ($productIds as $productId) {
            try {
                $bestPlan = json_decode($this->config->getCatalogSetting($productId, "bestPlan"), true);

                if ($bestPlan)
                    $productsData[$productId] = [
                        'product_id'      => $productId,
                        'plan_count'      => $bestPlan['count'],
                        'plan_amount'     => $bestPlan['amount'],
                        'plan_source'     => $bestPlan['source'],
                        'plan_percentage' => $bestPlan['percentage']
                    ];

            } catch (\Exception $e) {
                $this->logger->log(
                    'error', 
                    'FinanceData Controller > Error getting plan data',
                     ['product_id' => $productId, 'exception' => $e->getMessage()]
                    );
            }
        }

        return $result->setData(['products' => $productsData]);
    }
}