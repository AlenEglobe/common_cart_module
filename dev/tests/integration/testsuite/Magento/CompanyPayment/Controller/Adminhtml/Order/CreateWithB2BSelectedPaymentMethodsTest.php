<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyPayment\Controller\Adminhtml\Order;

use Magento\Framework\App\Config\ScopeConfigInterface;

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
class CreateWithB2BSelectedPaymentMethodsTest extends AbstractCreateTest
{
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
                        'payment/fake_vault/active' => 0,
                        'payment/fake/active' => 0,
                        'btob/default_b2b_payment_methods/applicable_payment_methods' => 1,
                        'btob/default_b2b_payment_methods/available_payment_methods' => 'checkmo'
                    ]
                ]
            ],
            'companyPaymentMethodConfig' => [
                'applicable_payment_method' => 0,
                'use_config_settings' => 1
            ],
            'expectedResultCompanyCustomer' => [
                'enabled' => [
                    'payment_form_checkmo'
                ],
                'disabled' => [
                    'payment_form_purchaseorder'
                ]
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
