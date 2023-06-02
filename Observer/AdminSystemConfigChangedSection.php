<?php
namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class AdminSystemConfigChangedSection implements ObserverInterface
{
    /**
     * @var \Mobbex\Webpay\Helper\Instantiator
    */
    protected $instantiator;

    public function __construct(\Mobbex\Webpay\Helper\Instantiator $instantiator)
    {
        $instantiator->setProperties($this, ['config']);
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if(!$this->config->get('own_dni_column') && empty($this->config->get('dni_column')))
            $this->config->save('own_dni_field', 1);
        else if(!empty($this->config->get('dni_column')) && $this->config->get('own_dni_column'))
            $this->config->save('own_dni_field', 0);
    }
}