<?php

namespace Mobbex\Webpay\Helper;

use Magento\Sales\Model\Order;

class Mobbex extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $scopeConfig;
    public $order;
    public $modelOrder;
    public $cart;

    protected $_objectManager;
    protected $log;
    protected $urlBuilder;
    protected $imageHelper;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Order $modelOrder,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\ObjectManagerInterface $_objectManager,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Catalog\Helper\Image $imageHelper
    ) {
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;

        $this->_objectManager = $_objectManager;
        $this->log = $logger;
        $this->urlBuilder = $urlBuilder;
        $this->imageHelper = $imageHelper;
    }

    private function getHeaders()
    {
        return array(
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . $this->scopeConfig->getValue(
                'payment/webpay/api_key',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'x-access-token: ' . $this->scopeConfig->getValue(
                'payment/webpay/access_token',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
        );
    }

    public function createCheckout()
    {
        $curl = curl_init();

        // get checkout object
        $checkout = $this->_objectManager->get('Magento\Checkout\Model\Type\Onepage')->getCheckout();

        // get order object
        $this->order->loadByIncrementId($checkout->getLastRealOrder()->getEntityId());

        // get extra order data
        $orderData = $this->modelOrder->load($checkout->getLastRealOrder()->getEntityId());

        // get oder id
        $orderId = $checkout->getLastRealOrderId();

        // set order description as #ORDERID
        $description = __('Orden #') . $checkout->getLastRealOrderId();

        // get order amount
        $orderAmount = round($this->order->getData('base_grand_total'), 2);

        // get customer's email
        $customerEmail = $orderData->getCustomerEmail();

        // ------------------------------

        $items = array();
        $orderedItems = $this->order->getAllVisibleItems();

        foreach ($orderedItems as $item) {
            $product = $item->getProduct();

            // print_r($product->debug());
            // print_r($item->debug());

            $items[] = array(
                "image" => $this->imageHelper->init($product, 'product_small_image')->getUrl(),
                "description" => $product->getName(),
                "quantity" => $item->getQtyOrdered(),
                "total" => round($product->getPrice(), 2),
            );
        }

        $returnUrl = $this->urlBuilder->getUrl('webpay/payment/paymentreturn', [
            '_secure' => true,
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                "order_id" => $orderId
            ],
        ]);

        $webhook = $this->urlBuilder->getUrl('webpay/payment/webhook', [
            '_secure' => true,
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                "order_id" => $orderId
            ],
        ]);

        // Create data
        $data = array(
            'reference' => $orderId,
            'currency' => 'ARS',
            'email' => $customerEmail,
            'description' => $description,
            // Test Mode
            'test' => $this->scopeConfig->getValue(
                'payment/webpay/test_mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'return_url' => $returnUrl,
            'items' => $items,
            'webhook' => $webhook,
            // 'options' => MobbexHelper::getOptions(),
            'redirect' => 0,
            'total' => (float) $orderAmount,
        );

        // print_r($data);

        $this->log->debug("Checkout Data", $data);

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://mobbex.com/p/checkout",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $this->getHeaders(),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $this->log->error("Checkout Error", $err);

            return false;
        } else {
            $res = json_decode($response, true);

            // print_r($res);
            $this->log->debug("Checkout Response", $res);

            // Set State and Status
            $this->order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT)->save();

            return $res['data']['url'];
        }
    }



    public function checkPrivateToken() {

    }
}
