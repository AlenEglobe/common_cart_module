<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Controller\Adminhtml\Order;

use Magento\Framework\Exception\AuthenticationException;
use Magento\CompanyShipping\Model\CompanyShippingMethodFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Test Class for B2B shipping method settings by admin create order flow with selected shipping methods
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class CreateWithSelectedShippingMethodsAbstract extends CreateAbstract
{
    /**
     * @var CompanyShippingMethodFactory
     */
    protected $companyShippingMethodFactory;

    /**
     * @inheritDoc
     *
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->companyShippingMethodFactory = $this->_objectManager->get(CompanyShippingMethodFactory::class);
    }

    /**
     * Config data provider with B2B Selected Shipping Methods Enabled
     * @return array
     */
    public function shippingConfigDataProviderWithSelectedShippingMethodsEnabled()
    {
        return [
            'defaultScope' => [
                'config_data' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        '' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '1',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 1,
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping,tablerate',
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
                            'carriers/freeshipping/active' => '1',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 1,
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping,tablerate',
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
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping,tablerate',
                            'btob/order_approval/purchaseorder_active' => 0
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '1',
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
                            'btob/default_b2b_shipping_methods/available_shipping_methods' => 'freeshipping,tablerate',
                            'btob/order_approval/purchaseorder_active' => 1
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
                            'carriers/flatrate/active' => '1',
                            'carriers/freeshipping/active' => '1',
                            'carriers/tablerate/active' => '1',
                            'carriers/tablerate/condition_name' => 'package_qty',
                        ]
                    ],
                ]
            ]
        ];
    }
}
