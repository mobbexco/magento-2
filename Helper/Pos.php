<?php

namespace Mobbex\Webpay\Helper;

class Pos
{
    /** @var \Mobbex\Webpay\Helper\Logger */
    private $logger;

    /** @var \Mobbex\Webpay\Helper\Order */
    private $orderHelper;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    private $cf;

    /** @var \Magento\Checkout\Model\Session */
    private $checkoutSession;

    public function __construct(
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Order $orderHelper,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->logger             = $logger;
        $this->orderHelper        = $orderHelper;
        $this->cf                 = $customFieldFactory;
        $this->checkoutSession    = $checkoutSession;
    }

    /**
     * Create a payment intent for the posUid provided and current order.
     * 
     * @param string $posUid
     * 
     * @return array
     */
    public function createPaymentIntent($posUid)
    {
        $order = $this->checkoutSession->getLastRealOrder();

        // Build intent using order data (same as checkout)
        $checkoutData = $this->orderHelper->buildCheckoutData($order);
        $intent = new \Mobbex\Modules\Pos(
            $checkoutData['id'],
            $posUid,
            $checkoutData['total'],
            $checkoutData['webhook'],
            [],
            $checkoutData['installments'],
            $checkoutData['customer'],
            'mobbexPosOperationRequest',
            $checkoutData['description'],
            $checkoutData['reference']
        );

        $this->logger->log(
            'debug',
            "Mobbex\Webpay\Helper\Pos::createPaymentIntent | POS Payment Intent Response: ",
            $intent->response
        );

        return $intent->response;
    }

    /**
     * Get the admin user id if logged as customer.
     * 
     * @return int
     */
    public function getLacAdminUserId()
    {
        $adminUserGetter = \Magento\Framework\App\ObjectManager::getInstance()->get(
            \Magento\LoginAsCustomerApi\Api\GetLoggedAsCustomerAdminIdInterface::class
        );

        return (int) ($adminUserGetter ? $adminUserGetter->execute() : 0);
    }

    /**
     * Get all POS terminals available from the Mobbex entity.
     * 
     * @return array[] List of POS terminals
     */
    public function getAllPosList()
    {
        $result = \Mobbex\Api::request([
            'method' => 'GET',
            'uri'    => "pos",
        ]);

        if (empty($result['docs']) || !is_array($result['docs'])) {
            $this->logger->log('error', 'No POS terminals found in Mobbex account', $result);
            return [];
        }

        return $result['docs'];
    }

    /**
     * List all POS available for the admin user provided.
     * 
     * @param int $adminUserId
     * 
     * @return array[]
     */
    public function getUserAssignedPosList($adminUserId)
    {
        $allPos = $this->getAllPosList();

        if (!$allPos)
            return [];

        $userPos = $this->getUserAssignedPosUids($adminUserId);

        // Return if the user has not assigned POS
        if (!is_array($userPos) || empty($userPos))
            return [];

        return array_filter($allPos, function($pos) use ($userPos) {
            return isset($pos['uid']) && in_array($pos['uid'], $userPos);
        });
    }

    /**
     * List UIDs of the all POS assigned for the admin user provided.
     * 
     * @param int $adminUserId
     * 
     * @return string[] Only the POS UIDs
     */
    public function getUserAssignedPosUids($adminUserId)
    {
        if (!$adminUserId)
            return [];

        /** @var \Mobbex\Webpay\Model\CustomField */
        $customField = $this->cf->create();
        $posListJson = $customField->getCustomField($adminUserId, 'user', 'pos_list');

        $selectedPos = json_decode($posListJson, true);
        return is_array($selectedPos) ? $selectedPos : [];
    }
}
