<?php

namespace Mobbex\Webpay\Controller\Payment;

class Task extends \Magento\Framework\App\Action\Action
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Model\OrderRepository */
    public $orderRepository;

    /** @var \Mobbex\Webpay\Model\OrderUpdate */
    public $orderUpdate;

    /**
     * A list of callbacks allowed to execute in the controller.
     *
     * @var string[]
     */
    public $callbacks = [
        'cancel_old_pending_orders' => 'cancelOldPendingOrders'
    ];

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Model\OrderRepository $orderRepository,
        \Mobbex\Webpay\Model\OrderUpdate $orderUpdate
    ) {
        parent::__construct($context);

        $this->sdk             = $sdk;
        $this->logger          = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderUpdate     = $orderUpdate;

        $this->sdk->init();
    }

    public function execute()
    {
        try {
            $name = $this->_request->getParam('name');

            // Throw exception if task does not exists
            if (empty($this->callbacks[$name]))
                throw new \Exception("No tasks found for the name '$name'");

            return $this->logger->createJsonResponse(
                'debug',
                'Task excecution result: ',
                $this->{$this->callbacks[$name]}()
            );
        } catch (\Exception $e) {
            return $this->logger->createJsonResponse(
                'error',
                'Task excecution error: ' . $e->getMessage(),
                isset($e->data) ? $e->data : []
            );
        }
    }

    public function cancelOldPendingOrders()
    {
        $newOrders = $this->orderRepository->getNewOrders();

        foreach ($newOrders as $order) {
            $operation = \Mobbex\Repository::getOperationFromReference(
                \Mobbex\Modules\Checkout::generateReference($order->getEntityId())
            );

            if (!$operation)
                $this->orderUpdate->cancelOrder($order);

            // Sleeps 1 seccond to prevent api block
            sleep(1);
        }

        return 'OK';
    }
}
