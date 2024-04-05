<?php

namespace CommonCart\CommonCartModule\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class UpgradeData implements UpgradeDataInterface
{
    private $eavSetupFactory;
    private $eavConfig;
    private $attributeSetFactory;

    public function __construct(
        EavSetupFactory $eavSetupFactory,
        Config $eavConfig,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        // Check if we're running an upgrade (version comparison)
        if ($context->getVersion() && version_compare($context->getVersion(), '1.0.0', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            $customerEntity = $this->eavConfig->getEntityType('customer');
            $attributeSetId = $customerEntity->getDefaultAttributeSetId();
            $attributeSet = $this->attributeSetFactory->create();
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

            $eavSetup->addAttribute(
                Customer::ENTITY,
                'commonCart_attribute',
                [
                    'type' => 'int',
                    'backend' => '',
                    'frontend' => '',
                    'label' => 'Common Cart',
                    'input' => 'boolean',
                    'class' => '',
                    'source' => '',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => 0,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'unique' => false,
                    'apply_to' => 'simple',
                ]
            );

            $attribute = $this->eavSetup->getEavConfig()->getAttribute(
                Customer::ENTITY,
                'commonCart_attribute'
            )->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer'],
            ]);
            $attribute->save();
        }

        $setup->endSetup();
    }
}
