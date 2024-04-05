<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company;

use Magento\Customer\Model\Customer;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Test class for Magento\Eav\Setup\EavSetup.
 * @magentoDbIsolation enabled
 */
class EavSetupTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var EavSetup
     */
    private $eavSetup;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->eavSetup = $objectManager->create(EavSetup::class);
    }

    /**
     * Verify that add attribute method throws exception if provided attribute_code is reserved by the system.
     *
     * If an EAV attribute that is user defined, its respective attribute code is set
     * to a getter field used by Company model (e.g. company_name), then an exception is raised,
     * and the attribute save does not proceed to completion
     */
    public function testAddAttributeWithCodeReservedBySystemThrowException()
    {
        $attributeCode = 'company_name';
        $attributeData = [
            'type'         => 'varchar',
            'label'        => 'Company Name',
            'input'        => 'text',
            'required'     => false,
            'visible'      => true,
            'user_defined' => true,
            'position'     => 999,
            'system'       => 0,
        ];

        $this->expectException(LocalizedException::class);
        $message = "The attribute code '{$attributeCode}' is reserved by system. Please try another attribute code";
        $this->expectExceptionMessage($message);

        $this->eavSetup->addAttribute(Customer::ENTITY, $attributeCode, $attributeData);
    }
}
