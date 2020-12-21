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

        /* Add product plans attributes */

        if (version_compare($context->getVersion(), '1.1.3', '<=')) {

            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

            $plans = 
            [
                'ahora_3' => 'Ahora 3', 
                'ahora_6' => 'Ahora 6', 
                'ahora_12' => 'Ahora 12', 
                'ahora_18' => 'Ahora 18'
            ];

            foreach ($plans as $key => $label) {
                $eavSetup->addAttribute(
                    \Magento\Catalog\Model\Product::ENTITY,
                    $key,
                    [
                        'type' => 'int',
                        'label' => $label,
                        'input' => 'boolean',
                        'required' => false,
                        'sort_order' => 30,
                        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                        'wysiwyg_enabled' => false,
                        'group' => 'Product Details',
                        'note' => 'Activar para que NO aparezca durante la compra',
                    ]
                );
            }
        }

        $setup->endSetup();
    }
}