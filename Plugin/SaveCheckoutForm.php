<?php

namespace Mobbex\Webpay\Plugin;

use Mobbex\Webpay\Helper\Config;
use Mobbex\Webpay\Model\CustomFieldFactory;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Checkout\Api\Data\ShippingInformationInterface;

/**
 * Class SaveCheckoutForm
 * @package Mobbex\Webpay\Plugin
 */
class SaveCheckoutForm
{
    /**
     * @param Config $config
     * @param CustomFieldFactory $customFieldFactory
     * @param Session $customerSession
     */
    public function __construct(Config $config, CustomFieldFactory $customFieldFactory, Session $customerSession)
    {
        $this->config = $config;
        $this->customFieldFactory = $customFieldFactory;
        $this->customerSession = $customerSession;
    }

    /**
     * Save Mobbex fields from checkout form.
     * 
     * Exectued before Magento\Checkout\Model\ShippingInformationManagement::saveAddressInformation
     * 
     * @param ShippingInformationManagement $subject
     * @param int|string $cartId
     * @param ShippingInformationInterface $addressInformation
     * 
     * @return array
     */
    public function beforeSaveAddressInformation(ShippingInformationManagement $subject, $cartId, ShippingInformationInterface $addressInformation)
    {
        // Exit if dni field option is disabled
        if (!$this->config->get('own_dni_field'))
            return [$cartId, $addressInformation];

        // Get extension attributes
        $extAttributes = $addressInformation->getShippingAddress()->getExtensionAttributes();
        if (!$extAttributes || !method_exists($extAttributes, 'getMbbxDni'))
            return [$cartId, $addressInformation];

        // Get dni field value
        $dni = $extAttributes->getMbbxDni();

        if ($dni) {
            $customField = $this->customFieldFactory->create();

            // Create custom field of quote or current user if logged in
            $customerId = $this->customerSession->getCustomer()->getId();
            $object     = $customerId ? 'customer' : 'quote';
            $rowId      = $customerId ? $customerId : $cartId;

            // Save custom field
            $customField->saveCustomField($rowId, $object, 'dni', $dni);
        }

        return [$cartId, $addressInformation];
    }
}