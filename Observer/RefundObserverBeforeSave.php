<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Action\Context;
use Mobbex\Webpay\Helper\Data;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class RefundObserverBeforeSave
 * @package Mobbex\Webpay\Observer
 */
class RefundObserverBeforeSave implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $_helper;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    public function __construct(Context $context, Data $_helper) {
        $this->messageManager = $context->getMessageManager();
        $this->_helper = $_helper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getData('creditmemo');
        $amount = $creditMemo->getGrandTotal();

        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();
        
        $paymentId = $payment->getAdditionalInformation('mobbex_data')['payment']['id'];
        $paymentMethod = $payment->getMethodInstance()->getCode();

        if ($paymentMethod != 'webpay') {
            return;
        }
        
        // If amount is invalid throw exception 
        if ($amount <= 0) {
            $message = __('Refund Error: Sorry! This is not a refundable transaction.');
            $this->messageManager->addErrorMessage($message);
            Data::log($message, 'mobbex_error_' . date('m_Y') . '.log');

            throw new \Magento\Framework\Exception\LocalizedException(new \Magento\Framework\Phrase($message));
        }

        $this->processRefund($amount, $paymentId);
    }

    public function processRefund($amount, $paymentId)
    {
        $curl = curl_init();

        $headers = $this->_helper->mobbex->getHeaders();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.mobbex.com/p/operations/' . $paymentId . '/refund',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode(['total' => floatval($amount)]),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $result = json_decode($response);

        if (empty($err) && $result->result) {
            return true;
        } else {
            $message = empty($err) ? __('Refund Error: Sorry! This is not a refundable transaction.') : 'Refund Error:' . print_r($err, true);

            $this->messageManager->addErrorMessage($message);
            Data::log($message, 'mobbex_error_' . date('m_Y') . '.log');

            throw new \Magento\Framework\Exception\LocalizedException(new \Magento\Framework\Phrase($message));
        }
    }
}