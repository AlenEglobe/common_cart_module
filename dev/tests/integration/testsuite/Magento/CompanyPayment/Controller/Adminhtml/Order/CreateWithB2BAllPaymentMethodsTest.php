<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyPayment\Controller\Adminhtml\Order;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Backend\Model\Session\Quote as SessionQuote;

/**
 * Test Class for B2B payment method settings by admin create order flow
 *
 * @magentoDataFixture Magento/Customer/_files/customer.php
 * @magentoDataFixture Magento/Company/_files/company_with_structure.php
 * @magentoDataFixture Magento/Company/_files/companies_with_different_sales_representatives.php
 * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class CreateWithB2BAllPaymentMethodsTest extends AbstractCreateTest
{

    /**
     * @inheridoc
     */
    protected $uri = 'backend/sales/order_create';

    /**
     * @inheridoc
     */
    protected $resource = 'Magento_Sales::create';

    /**
     * Tests ACL.
     *
     * @param string $actionName
     * @param boolean $reordered
     * @param string $expectedResult
     *
     * @magentoAppIsolation enabled
     */
    public function testGetAclResource()
    {
        $this->_objectManager->get(SessionQuote::class)->setReordered(false);
        $orderController = $this->_objectManager->get(
            \Magento\Sales\Controller\Adminhtml\Order\Stub\OrderCreateStub::class
        );

        $this->getRequest()->setActionName('index');

        $method = new \ReflectionMethod(\Magento\Sales\Controller\Adminhtml\Order\Create::class, '_getAclResource');
        $method->setAccessible(true);
        $result = $method->invoke($orderController);
        $this->assertEquals($result, 'Magento_Sales::create');
    }

    /**
     * @inheritDoc
     */
    protected function companyPaymentMethodsTestData()
    {
        return [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 1,
                            'payment/purchaseorder/active' => 1,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 0,
                    'use_config_settings' => 1
                ],
                'expectedResultCompanyCustomer' => [
                    'enabled' => [
                        'payment_form_checkmo',
                        'payment_form_purchaseorder'
                    ],
                    'disabled' => []
                ],
                'expectedResultNonCompanyCustomer' => [
                    'enabled' => [
                        'payment_form_checkmo',
                        'payment_form_purchaseorder'
                    ],
                    'disabled' => []
                ]
            ];
    }
}
