<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder\Customer;

use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test purchase orders enabled query
 */
class IsEnabledTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    private const QUERY =  <<<QRY
{
    customer {
        purchase_orders_enabled
    }
}
QRY;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->getCustomerHeaders = Bootstrap::getObjectManager()->get(GetCustomerHeaders::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(PurchaseOrderCompanyConfig::class, ['company_id' => '$company.id$'])
    ]
    public function testPurchaseOrdersCompanyEnabled()
    {
        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders_enabled' => true
                ]
            ],
            $this->graphQlQuery(
                self::QUERY,
                [],
                '',
                $this->getCustomerHeaders->execute()
            )
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(
            PurchaseOrderCompanyConfig::class,
            [
                'company_id' => '$company.id$',
                'is_purchase_order_enabled' => false
            ]
        )
    ]
    public function testPurchaseOrdersCompanyDisabled()
    {
        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders_enabled' => false
                ]
            ],
            $this->graphQlQuery(
                self::QUERY,
                [],
                '',
                $this->getCustomerHeaders->execute()
            )
        );
    }

    #[
        Config('btob/website_configuration/company_active', 0),
        DataFixture(Customer::class, as: 'customer')
    ]
    public function testPurchaseOrdersWebsiteConfigCompanyDisabled()
    {
        $this->expectExceptionMessage('Company feature is not available.');
        $this->graphQlQuery(
            self::QUERY,
            [],
            '',
            $this->getCustomerHeaders->execute()
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 0),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(PurchaseOrderCompanyConfig::class, ['company_id' => '$company.id$'])
    ]
    public function testPurchaseOrdersWebsiteConfigPurchaseOrderDisabled()
    {
        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders_enabled' => false
                ]
            ],
            $this->graphQlQuery(
                self::QUERY,
                [],
                '',
                $this->getCustomerHeaders->execute()
            )
        );
    }
}
