<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

if (interface_exists('\Magento\Framework\App\CsrfAwareActionInterface')) {
    abstract class WebhookBase extends Action implements CsrfAwareActionInterface
    {

        /**
         * @param RequestInterface $request
         * @return InvalidRequestException|null
         */
        public function createCsrfValidationException(RequestInterface $request)
        {
            return null;
        }

        /**
         * @param RequestInterface $request
         * @return bool|null
         */
        public function validateForCsrf(RequestInterface $request)
        {
            return true;
        }
    }
} else {
    abstract class WebhookBase extends Action
    {
    }
}
