<?php

namespace Mobbex\Webpay\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($context->getVersion() && version_compare($context->getVersion(), '2.0.1') < 0) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'ahora_3',
                [
                    'type' => 'int',
                    'label' => 'Ahora 3',
                    'input' => 'boolean',
                    'required' => false,
                    'sort_order' => 30,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'wysiwyg_enabled' => false,
                    'group' => 'Product Details',
                    'note' => 'Activar para que NO aparezca durante la compra',
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'ahora_6',
                [
                    'type' => 'int',
                    'label' => 'Ahora 6',
                    'input' => 'boolean',
                    'required' => false,
                    'sort_order' => 30,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'wysiwyg_enabled' => false,
                    'is_html_allowed_on_front' => false,
                    'group' => 'Product Details',
                    'note' => 'Activar para que NO aparezca durante la compra',
                ]
            );
            
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'ahora_12',
                [
                    'type' => 'int',
                    'label' => 'Ahora 12',
                    'input' => 'boolean',
                    'required' => false,
                    'sort_order' => 30,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'wysiwyg_enabled' => false,
                    'group' => 'Product Details',
                    'note' => 'Activar para que NO aparezca durante la compra',
                ]
            );

            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                'ahora_18',
                [
                    'type' => 'int',
                    'label' => 'Ahora 18',
                    'input' => 'boolean',
                    'required' => false,
                    'sort_order' => 30,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'wysiwyg_enabled' => false,
                    'is_html_allowed_on_front' => false,
                    'group' => 'Product Details',
                    'note' => 'Activar para que NO aparezca durante la compra',
                ]
            );

        }

        $setup->endSetup();
    }
}