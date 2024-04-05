<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Plugin\Quote;

use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\CompanyShipping\Model\CompanyShippingMethodFactory;
use Magento\CompanyShipping\Model\Source\CompanyApplicableShippingMethod;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Test Class for Magento\CompanyShipping\Plugin\Model\Quote\AddressPlugin
 * which update quote shipping rates based on B2B shipping settings
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class AddressPluginTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CompanyShippingMethodFactory
     */
    private $companyShippingMethodFactory;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->companyRepository = $this->objectManager->create(CompanyRepositoryInterface::class);
        $this->companyShippingMethodFactory = $this->objectManager->get(CompanyShippingMethodFactory::class);
        $this->configFactory = $this->objectManager->get(ConfigFactory::class);
    }

    /**
     * Test available shipping rates for non company customer with:
     * B2B applicable shipping methods enabled
     * B2B applicable shipping methods is: free shipping
     * Global sales shipping methods are: free shipping, flat rate
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testNonCompanyCustomerStoreFrontWithB2BApplicableShippingMethod(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(3, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('flatrate', $shippingRates[1]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[2]->getCarrier());
    }

    /**
     * Test available shipping rates for company customer with:
     * Company B2B shipping methods is default
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testCompanyCustomerStoreFrontWithDefaultB2BShippingMethodsAndSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(2, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[1]->getCarrier());
    }

    /**
     * Test available shipping rates for company customer with:
     * Company B2B shipping methods is B2BShippingMethods
     * B2B settings ALL shipping methods enabled
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithAllShippingMethodsEnabled
     */
    public function testCompanyCustomerStoreFrontWithB2BShippingMethodsAndAllShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::B2B_SHIPPING_METHODS_VALUE,
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(3, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('flatrate', $shippingRates[1]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[2]->getCarrier());
    }

    /**
     * Test available shipping rates for company customer with:
     * B2B applicable shipping methods enabled
     * B2B applicable shipping methods are: free shipping
     * Global sales shipping methods free shipping is disabled
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @dataProvider shippingConfigDataProviderWithSelectedDisabledShippingMethods
     */
    public function testCompanyCustomerStoreFrontWithB2BApplicableShippingMethodAndDisabledAvailableShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertEmpty($shippingRates);
    }

    /**
     * Test available shipping rates for company customer with:
     * Company B2B shipping methods is B2BShippingMethods
     * Company Purchase Order Enabled
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testCompanyCustomerStoreFrontWithB2BShippingMethodsAndSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );
        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);
        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::B2B_SHIPPING_METHODS_VALUE,
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(2, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[1]->getCarrier());
    }

    /**
     * Test available shipping rates for company customer with:
     * Company B2B shipping methods is All shippingMethods
     * Company Purchase Order Enabled
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testCompanyCustomerStoreFrontWithAllShippingMethodsAndSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );
        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);
        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::ALL_SHIPPING_METHODS_VALUE,
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(3, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('flatrate', $shippingRates[1]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[2]->getCarrier());
    }

    /**
     * Test available shipping rates for company customer with:
     * Company B2B shipping methods is selected shipping methods: free shipping
     * Company Purchase Order Enabled
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testCompanyCustomerStoreFrontWithSelectedShippingMethodsAndB2BSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );
        $this->companyRepository->save($company);
        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::SELECTED_SHIPPING_METHODS_VALUE,
                'available_shipping_methods' => 'freeshipping',
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(1, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
    }

    /**
     * Test available shipping rates for non company and some customer with:
     * Company B2B shipping methods is selected shipping methods: free shipping
     * Company Purchase Order Enabled
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testNonCompanyCustomerStoreFrontWithSelectedShippingMethodsAndB2BSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );
        $this->companyRepository->save($company);
        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::SELECTED_SHIPPING_METHODS_VALUE,
                'available_shipping_methods' => 'freeshipping',
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(3, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('flatrate', $shippingRates[1]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[2]->getCarrier());
    }

    /**
     * Test available shipping rates for different company and company customer with:
     * Company B2B shipping methods is selected shipping methods: free shipping
     * Company Purchase Order Enabled
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate, table rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Company/_files/company.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     */
    public function testDiffCompanyCustomerStoreFrontWithSelectedShippingMethodsAndB2BSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $customerId = 1;
        $quote = $this->cartRepository->getForCustomer($customerId);
        //replace quote customer to company customer
        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $diffCompanyCustomer = $this->customerRepository->get('admin@magento.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );
        $this->companyRepository->save($company);
        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::SELECTED_SHIPPING_METHODS_VALUE,
                'available_shipping_methods' => 'freeshipping',
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();
        $quote->setCustomerId($diffCompanyCustomer->getId());
        $quote->setCustomer($diffCompanyCustomer);
        $quote->collectTotals()->save();
        /** @var AddressInterface $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();
        //collect shipping rates
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingRates = $shippingAddress->getAllShippingRates();
        static::assertNotEmpty($shippingRates);
        static::assertCount(2, $shippingRates);
        static::assertEquals('freeshipping', $shippingRates[0]->getCarrier());
        static::assertEquals('tablerate', $shippingRates[1]->getCarrier());
    }

    /**
     * Config data provider with B2B All Shipping Methods Enabled
     * @return array
     */
    public function shippingConfigDataProviderWithAllShippingMethodsEnabled()
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
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 0,
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
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 0,
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
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 0,
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
                            'btob/default_b2b_shipping_methods/applicable_shipping_methods' => 0,
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

    /**
     * Update scope config settings
     * @param array $configData
     * @throws \Exception
     */
    private function setConfigValues($configData)
    {
        foreach ($configData as $scope => $data) {
            foreach ($data as $scopeCode => $scopeData) {
                foreach ($scopeData as $path => $value) {
                    $config = $this->configFactory->create();
                    $config->setScope($scope);

                    if ($scope == ScopeInterface::SCOPE_WEBSITES) {
                        $config->setWebsite($scopeCode);
                    }

                    if ($scope == ScopeInterface::SCOPE_STORES) {
                        $config->setStore($scopeCode);
                    }

                    $config->setDataByPath($path, $value);
                    $config->save();
                }
            }
        }
    }
}
