<?php

namespace Mobbex\Webpay\Block;

class FinanceWidget extends \Magento\Backend\Block\Template
{
    /** @var \Magento\Backend\Block\Template\Context */
    public $context;

    /** @var \Magento\Framework\Registry */
    public $registry;

    /** @var \Magento\Framework\Pricing\Helper\Data */
    public $priceHelper;

    /** @var \Magento\Checkout\Model\Session */
    public $checkoutSession;

    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** Total amount to finance */
    public $total = 0;

    /** Sources url to get financial information */
    public $sourcesUrl;

    /** Featured innstallments configuration */
    public $featuredInstallments = null;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->sdk              = $sdk;
        $this->config           = $config;
        $this->logger           = $logger;
        $this->registry         = $registry;
        $this->priceHelper      = $priceHelper;
        $this->checkoutSession  = $checkoutSession;

        // Init sdk and get action name
        $this->sdk->init();
        $action = $this->_request->getFullActionName();

        $this->logger->log('debug', 'FinanceWidget Block > Construct', $action);

        $this->featuredInstallments = $this->getFeaturedInstallments();

        try {
            if ($action == 'catalog_product_view') {
                $this->productPage();
            } elseif ($action == 'checkout_cart_index') {
                $this->cartPage();
            } else {
                throw new \Exception("Construct Invalid action $action");
            }
        } catch (\Exception $e) {
            if ($e->getMessage())
                $this->logger->log('error', 'FinanceWidget Block > ', $e->getMessage());

            return $this->getLayout()->unsetElement('mbbx.finance.widget');
        }
    }

    public function productPage()
    {
        $product = $this->registry->registry('product');
        
        if (!$this->config->get('financial_active'))
            throw new \Exception('productPage Called on product when is disabled');
        
        if (!$product)
            throw new \Exception('productPage Invalid product');
        
        if (!$product->isSalable())
            throw new \Exception;

        $product_id = $product->getId();
        $total      = $product->getPriceInfo()->getPrice('final_price')->getValue();

        $data = 
            [
                'mbbxTotal'      => $total,
                'mbbxProductIds' => [$product_id],
            ];

        $this->sourcesUrl = $this->getUrl("sugapay/payment/sources", 
            [
                '_query' => $data,
            ]
        );

        $this->logger->log('debug', 'FinanceWidget Block > productPage', 
            [
                'total'      => $total,
                'product'    => $product_id,
                'sourcesUrl' => $this->sourcesUrl,
            ]
        );
    }

    public function cartPage()
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$this->config->get('finance_widget_on_cart'))
            throw new \Exception('cartPage Called on cart when is disabled');
        
        if (!$quote)
            throw new \Exception('cartPage Invalid quote');
        
        if (!$quote->hasItems())
            throw new \Exception;

        $total = $quote->getGrandTotal();

        foreach ($quote->getAllVisibleItems() as $item)
            $products[] = $item->getProduct()->getId();

        $data =
            [
                'mbbxTotal'      => $total,
                'mbbxProductIds' => $products,
            ];

        $this->sourcesUrl = $this->getUrl("sugapay/payment/sources", 
            [
                '_query' => $data,
            ]
        );

        $this->logger->log('debug', 'FinanceWidget Block > cartPage', 
            [
                'total'      => $total,
                'products'   => $products,
                'sourcesUrl' => $this->sourcesUrl,
            ]
        );
    }

    private function getFeaturedInstallments()
    {
        if ($this->config->get('show_featured_installments') != '1')
            return null;

        if ($this->config->get('best_featured_installments') == '1')
            return '[]';

        if (!empty($this->config->get('custom_featured_installments')))
            return json_encode(preg_split('/\s*,\s*/', trim(
                $this->config->get('custom_featured_installments'
            ))));
    }
}