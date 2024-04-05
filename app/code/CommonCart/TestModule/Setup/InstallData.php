<?php

declare(strict_types=1);

namespace CommonCart\TestModule\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Class InstallData
 *
 * @package MageSpark\CustomerAttribute\Setup
 */
class InstallData implements InstallDataInterface
{
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * Constructor
     *
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function install(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);

        $customerSetup->addAttribute(Customer::ENTITY, 'common_cart', [
            'type' => 'int',
            'label' => 'Custom Attribute',
            'input' => 'boolean',
            'source' => '',
            'position' => 700,
            'visible' => true,
            'required' => false,
            'system' => false
        ]);

        // Mention here in which form you want to show created customer attribute
        $attribute = $customerSetup->getEavConfig()->getAttribute('customer', 'custom')
            ->addData([
                'used_in_forms' => [
                    'adminhtml_customer',
                    'adminhtml_checkout',
                    'customer_account_create',
                    'customer_account_edit'
                ]
            ]);

        $attribute->save();
    }
}
