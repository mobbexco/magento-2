<?php
namespace Mobbex\Webpay\Controller\Payment;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\RequestInterface;
use \Magento\Framework\App\Request\InvalidRequestException;

if (interface_exists('\Magento\Framework\App\CsrfAwareActionInterface')) {
    abstract class WebhookBase extends Action implements \Magento\Framework\App\CsrfAwareActionInterface
    {

        /**
         * @param RequestInterface $request
         * @return InvalidRequestException|null
         */
        public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
        {
            return null;
        }

        /**
         * @param RequestInterface $request
         * @return bool|null
         */
        public function validateForCsrf(RequestInterface $request): ?bool
        {
            return true;
        }

    }
} else {
    abstract class WebhookBase extends Action
    {
    }
}