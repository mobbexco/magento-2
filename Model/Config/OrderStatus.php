<?php

namespace Mobbex\Webpay\Model\Config;

/**
 * Class OrderStatus
 * @package Mobbex\Webpay\Model\Config
 */
class OrderStatus extends \Magento\Sales\Model\Config\Source\Order\Status
{
    /**
     * @var string[]
     */
    protected $_stateStatuses = null;

}
