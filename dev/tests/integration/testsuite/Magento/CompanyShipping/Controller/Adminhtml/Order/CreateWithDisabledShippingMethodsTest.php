<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Controller\Adminhtml\Order;

use Magento\CompanyShipping\Model\CompanyShippingMethodFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\ScopeInterface;

/**
 * Test Class for B2B shipping method settings by admin create order flow with disabled shipping methods
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class CreateWithDisabledShippingMethodsTest extends CreateAbstract
{
    /**
     * Test available shipping rates for company customer quote by admin create order with:
     * B2B applicable shipping methods enabled
     * B2B applicable shipping methods are: free shipping
     * Global sales shipping methods free shipping is disabled
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedDisabledShippingMethods
     * @throws \Exception
     */
    public function testLoadBlockShippingWithCompanyCustomerB2BApplicableShippingMethodsAndDisabledAvailableShipping(
        $configData
    ) {
        $this->setConfigValues($configData);
        $quote = $this->_objectManager->get(CartRepositoryInterface::class)->getForCustomer(1);
        //replace quote customer to company customer
        $companyCustomer = $this->_objectManager->get(CustomerRepositoryInterface::class)
            ->get('alex.smith@example.com');
        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->save();

        $session = $this->_objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue(
            [
                'customer_id' => $companyCustomer->getId(),
                'collect_shipping_rates' => 1,
                'store_id' => 1,
                'json' => true
            ]
        );
        $this->dispatch('backend/sales/order_create/loadBlock/block/shipping_method');
        $body = $this->getResponse()->getBody();

        self::assertStringContainsString('Sorry, no quotes are available for this order.', $body);
    }

    /**
     * Config data provider with B2B Selected Disabled Shipping Methods Enabled
     * @return array
     */
    public function shippingConfigDataProviderWithSelectedDisabledShippingMethods()
    {
        return [
            'defaultScope' => [
                'config_data' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        '' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '0',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 1,
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping',
                            'btob/order_approval/purchaseorder_active' => 0
                        ]
                    ],
                ]
            ],
            'defaultScopeWithPurchaseOrderEnabled' => [
                'config_data' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        '' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '0',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 1,
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping',
                            'btob/order_approval/purchaseorder_active' => 1
                        ]
                    ],
                ]
            ],
            'websiteScope' => [
                'config_data' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        '' => [
                            'carriers/flatrate/active' => '0',
                            'carriers/freeshipping/active' => '0',
                            'carriers/tablerate/active' => '0',
                            'carriers/tablerate/condition_name' => 'package_qty',
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 1,
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping',
                            'btob/order_approval/purchaseorder_active' => 0
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '0',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                        ]
                    ],
                ]
            ],
            'websiteScopeWithPurchaseOrderEnabled' => [
                'config_data' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        '' => [
                            'carriers/flatrate/active' => '0',
                            'carriers/freeshipping/active' => '0',
                            'carriers/tablerate/active' => '0',
                            'carriers/tablerate/condition_name' => 'package_qty',
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 1,
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping',
                            'btob/order_approval/purchaseorder_active' => 1
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '0',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                        ]
                    ],
                ]
            ]
        ];
    }
}
