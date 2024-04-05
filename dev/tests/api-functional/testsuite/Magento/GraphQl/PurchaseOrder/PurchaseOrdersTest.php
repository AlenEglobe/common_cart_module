<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Magento\Catalog\Test\Fixture\Product;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Role;
use Magento\Customer\Test\Fixture\Customer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrderRule\Test\Fixture\PurchaseOrderValidate;
use Magento\PurchaseOrderRule\Test\Fixture\Rule;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test purchase orders query
 */
class PurchaseOrdersTest extends GraphQlAbstract
{
    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(Customer::class, as: 'company_2_admin'),
        DataFixture(Customer::class, as: 'company_buyer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(Product::class, as: 'product'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$company_admin.id$'
            ],
            'company'
        ),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$company_2_admin.id$'
            ],
            'company_2'
        ),
        DataFixture(
            AssignCustomer::class,
            [
                'company_id' => '$company.entity_id$',
                'customer_id' => '$company_buyer.id$'
            ]
        ),
        DataFixture(
            Role::class,
            [
                'company_id' => '$company.entity_id$'
            ],
            'approver'
        ),
        DataFixture(
            Rule::class,
            [
                'company_id' => '$company.entity_id$',
                'approver_role_ids' => ['$approver.role_id$'],
                'created_by' => '$company_admin.id$'
            ],
            'rule'
        ),
        DataFixture(
            CustomerCart::class,
            [
                'customer_id' => '$company_buyer.id$'
            ],
            'quote_1'
        ),
        DataFixture(
            AddProductToCart::class,
            [
                'cart_id' => '$quote_1.id$',
                'product_id' => '$product.id$'
            ]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$quote_1.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$quote_1.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$quote_1.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$quote_1.id$']),
        DataFixture(
            PurchaseOrderFromQuote::class,
            [
                'cart_id' => '$quote_1.id$',
            ],
            'purchase_order_1'
        ),
        DataFixture(
            PurchaseOrderValidate::class,
            [
                'purchase_order_id' => '$purchase_order_1.entity_id$',
            ]
        ),
        DataFixture(
            CustomerCart::class,
            [
                'customer_id' => '$company_admin.id$'
            ],
            'quote_2'
        ),
        DataFixture(
            AddProductToCart::class,
            [
                'cart_id' => '$quote_2.id$',
                'product_id' => '$product.id$'
            ]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$quote_2.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$quote_2.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$quote_2.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$quote_2.id$']),
        DataFixture(
            PurchaseOrderFromQuote::class,
            [
                'cart_id' => '$quote_2.id$',
            ],
            'purchase_order_2'
        ),
    ]
    public function testResolve()
    {
        $this->checkResolveNoFilters();
        $this->checkResolveStatusFilter();
        $this->checkResolveCreatedDateFilter();
        $this->checkResolveCompanyFilter();
        $this->checkResolveRequireMyApprovalFilter();
    }

    private function checkResolveNoFilters()
    {
        $query = <<<QRY
{
    customer {
        purchase_orders {
            items {
                status
            }
            page_info {
                page_size
                current_page
                total_pages
            }
            total_count
        }
    }
}
QRY;

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [
                            [
                                'status' => strtoupper(PurchaseOrderInterface::STATUS_PENDING),
                            ],
                        ],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 1
                        ],
                        'total_count' => 1
                    ]
                ]
            ],
            $this->graphQlQuery(
                $query,
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            ),
            'checkResolveNoFilters test failed'
        );
    }

    private function checkResolveStatusFilter()
    {
        $query = <<<QRY
{
    customer {
        purchase_orders(filter: {status: APPROVAL_REQUIRED}) {
            items {
                status
            }
            page_info {
                page_size
                current_page
                total_pages
            }
            total_count
        }
    }
}
QRY;

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [
                        ],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 0
                        ],
                        'total_count' => 0
                    ]
                ]
            ],
            $this->graphQlQuery(
                $query,
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            ),
            'checkResolveStatusFilter test failed'
        );
    }

    private function checkResolveCreatedDateFilter()
    {
        $query = <<<QRY
{
    customer {
        purchase_orders(filter: {created_date: {from: "%s"}}) {
            items {
                status
            }
            page_info {
                page_size
                current_page
                total_pages
            }
            total_count
        }
    }
}
QRY;

        $oneDay = new \DateInterval('P1D');
        $queryPast = sprintf($query, (new \DateTime())->sub($oneDay)->format('Y-m-d H:i:s'));
        $queryFuture = sprintf($query, (new \DateTime())->add($oneDay)->format('Y-m-d H:i:s'));

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [
                            [
                                'status' => strtoupper(PurchaseOrderInterface::STATUS_PENDING),
                            ],
                        ],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 1
                        ],
                        'total_count' => 1
                    ]
                ]
            ],
            $this->graphQlQuery(
                $queryPast,
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            ),
            'checkResolveCreatedDateFilter test failed'
        );

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 0
                        ],
                        'total_count' => 0
                    ]
                ]
            ],
            $this->graphQlQuery(
                $queryFuture,
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            )
        );
    }

    private function checkResolveRequireMyApprovalFilter()
    {
        $query = <<<QRY
{
    customer {
        purchase_orders(filter: {require_my_approval: true}) {
            items {
                status
            }
            page_info {
                page_size
                current_page
                total_pages
            }
            total_count
        }
    }
}
QRY;

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [
                            ['status' => 'APPROVAL_REQUIRED'],
                        ],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 1
                        ],
                        'total_count' => 1
                    ]
                ]
            ],
            $this->graphQlQuery(
                $query,
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            )
        );

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 0
                        ],
                        'total_count' => 0
                    ]
                ]
            ],
            $this->graphQlQuery(
                $query,
                [],
                '',
                $this->getCustomerHeaders->execute('company_buyer')
            )
        );
    }

    private function checkResolveCompanyFilter()
    {
        $query = <<<QRY
{
    customer {
        purchase_orders(filter: {company_purchase_orders: true}) {
            items {
                status
            }
            page_info {
                page_size
                current_page
                total_pages
            }
            total_count
        }
    }
}
QRY;

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [
                            [
                                'status' => strtoupper(PurchaseOrderInterface::STATUS_PENDING),
                            ],
                            [
                                'status' => strtoupper(PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED),
                            ],
                        ],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 1
                        ],
                        'total_count' => 2
                    ]
                ]
            ],
            $this->graphQlQuery(
                $query,
                [],
                '',
                $this->getCustomerHeaders->execute('company_admin')
            )
        );

        $this->assertEquals(
            [
                'customer' => [
                    'purchase_orders' => [
                        'items' => [
                        ],
                        'page_info' => [
                            'page_size' => 20,
                            'current_page' => 1,
                            'total_pages' => 0
                        ],
                        'total_count' => 0
                    ]
                ]
            ],
            $this->graphQlQuery(
                $query,
                [],
                '',
                $this->getCustomerHeaders->execute('company_2_admin')
            )
        );
    }
}
