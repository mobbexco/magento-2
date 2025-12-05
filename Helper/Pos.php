<?php

namespace Mobbex\Webpay\Helper;

class Pos
{
    /** @var \Mobbex\Webpay\Helper\Logger */
    private $logger;

    /** @var \Mobbex\Webpay\Helper\Config */
    private $config;

    /** @var \Mobbex\Webpay\Helper\Order */
    private $orderHelper;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    private $cf;

    /** @var \Magento\Checkout\Model\Session */
    private $checkoutSession;

    public function __construct(
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Order $orderHelper,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->logger             = $logger;
        $this->config             = $config;
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
        // Build intent using order data (same as checkout)
        $order = $this->checkoutSession->getLastRealOrder();
        $checkoutData = $this->orderHelper->buildCheckoutData($order);

        // Get POS reference
        $terminal = $this->getSmartposTerminal($posUid);

        if (empty($terminal['reference']))
            throw new \Mobbex\Exception("No smartpos found for the POS UID $posUid");

        $res = \Mobbex\Api::request([
            'uri'    => "pos/{$terminal['reference']}/operation",
            'method' => 'POST',
            'body'   => \Mobbex\Platform::hook('mobbexPosOperationRequest', true, [
                'reference'    => $checkoutData['reference'] ?: \Mobbex\Modules\Checkout::generateReference($checkoutData['id']),
                'intent'       => \Mobbex\Platform::$settings['payment_mode'],
                'total'        => $checkoutData['total'],
                'currency'     => 'ARS', //$checkoutData['currency'] ?: 'ARS',
                'description'  => $checkoutData['description'] ?: "Pedido #" . $checkoutData['id'],
                'test'         => (bool) \Mobbex\Platform::$settings['test'],
                'webhook'      => $checkoutData['webhook'],
                'customer'     => $checkoutData['customer'],
                'installments' => $checkoutData['installments'],
                'sources'      => [],
                'timeout'      => (int) \Mobbex\Platform::$settings['timeout'],
            ], $checkoutData['id'])
        ]);

        return $res;
    }

    /**
     * Delete the payment intent status from the POS UID provided.
     * 
     * @param string $posUid
     * 
     * @return bool
     * 
     * @throws \Exception
     */
    public function deletePaymentIntent($posUid)
    {
        $terminal = $this->getSmartposTerminal($posUid);

        if (empty($terminal['reference']))
            throw new \Exception("No smartpos found for the POS UID $posUid");

        \Mobbex\Api::request([
            'method' => 'DELETE',
            'uri' => "pos/{$terminal['reference']}/operation",
        ]);

        return true;
    }

    /**
     * Get the payment intent status for the current order.
     * 
     * @return array {
     *   code: string,
     *   type: string,
     *   label: string
     * }
     */
    public function getPaymentIntentStatus()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        $statusTypes = [
            'order_status_approved',
            'order_status_cancelled',
            'order_status_in_process',
            'order_status_revision',
            'order_status_rejected',
            'order_status_refunded',
            'order_status_authorized'
        ];

        foreach ($statusTypes as $type) {
            $code = $this->config->get($type);

            if ($order->getStatus() == $code)
                return [
                    'code' => $code,
                    'type' => $type,
                    'label' => $order->getStatusLabel($code),
                ];
        }

        return [
            'code' => $order->getStatus(),
            'type' => 'unknown',
            'label' => $order->getStatusLabel($order->getStatus()),
        ];
    }

    /**
     * Get the smartpos terminal info from the POS UID provided.
     * 
     * @param string $posUid
     * 
     * @return array|null Terminal Info. Null if not found. {
     *  _id: string,
     *  uid: string,
     *  name: string,
     *  reference: string,
     *  type: string,
     *  subtype: string,
     *  status: string,
     *  updated: string,
     *  created: string,
     *  terminalSN: string | null
     * }
     * 
     * @throws \Exception
     */
    public function getSmartposTerminal($posUid)
    {
        $pos = \Mobbex\Api::request([
            'method' => 'GET',
            'uri' => "pos/$posUid",
        ]);

        if (empty($pos['terminals']) || !is_array($pos['terminals']))
            throw new \Exception("Not terminals found for the POS UID $posUid");

        $smartposTerminals = array_filter($pos['terminals'], function($terminal) {
            return $terminal['subtype'] === 'smartpos';
        });

        return reset($smartposTerminals) ?: null;
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
            'params' => [
                'noPaginate' => 1,
                'noShowDisabled' => 1,
            ],
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
