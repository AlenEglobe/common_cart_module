<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyPayment\Plugin\Quote;

use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\CompanyPayment\Model\CompanyPaymentMethodFactory;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as PurchaseOrderConfigRepositoryInterface;

/**
 * Test Class for Magento\CompanyPayment\Plugin\Quote\PaymentMethodManagementPlugin
 * which update quote payment methods list based on B2B payment settings
 *
 * @magentoDataFixture Magento/Customer/_files/customer.php
 * @magentoDataFixture Magento/Company/_files/company_with_structure.php
 * @magentoDataFixture Magento/Company/_files/companies_with_different_sales_representatives.php
 * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class PaymentMethodManagementPluginTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PaymentMethodManagementInterface
     */
    private $paymentManagment;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CompanyPaymentMethodFactory
     */
    private $companyPaymentMethodFactory;

    /**
     * @var PurchaseOrderConfigRepositoryInterface
     */
    private $purchaseOrderConfigRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->paymentManagment = $this->objectManager->get(PaymentMethodManagementInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $this->cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->configFactory = $this->objectManager->get(ConfigFactory::class);
        $this->companyPaymentMethodFactory = $this->objectManager->get(CompanyPaymentMethodFactory::class);
        $this->purchaseOrderConfigRepository = $this->objectManager->get(PurchaseOrderConfigRepositoryInterface::class);
    }

    public function testCompanyStoreFrontPaymentMethods()
    {
        foreach ($this->companyPaymentMethodsTestDataVariations() as $variation => $data) {
            $this->performTestCompanyStoreFrontPaymentMethods(
                $data['salesPaymentMethodsConfig'],
                $data['companyPaymentMethodConfig'],
                $data['expectedResultCompanyCustomer'],
                $data['expectedResultNonCompanyCustomer'],
                $variation
            );
        }
    }

    public function testCompanyStoreFrontPaymentMethodsWithPurchaseOrderEnabled()
    {
        foreach ($this->companyPaymentMethodsTestDataVariations() as $variation => $data) {
            $this->performTestCompanyStoreFrontPaymentMethodsWithPurchaseOrderEnabled(
                $data['salesPaymentMethodsConfig'],
                $data['companyPaymentMethodConfig'],
                $data['expectedResultCompanyCustomer'],
                $data['expectedResultNonCompanyCustomer'],
                $variation
            );
        }
    }

    /**
     * Test storefront payments for company/non company customers
     *
     * @param array $salesPaymentMethodsConfig
     * @param array $companyPaymentMethodConfig
     * @param array $expectedResultCompanyCustomer
     * @param array $expectedResultNonCompanyCustomer
     * @param string $variation
     *
     * @dataProvider companyPaymentMethodsConfigProvider
     */
    private function performTestCompanyStoreFrontPaymentMethods(
        array $salesPaymentMethodsConfig,
        array $companyPaymentMethodConfig,
        array $expectedResultCompanyCustomer,
        array $expectedResultNonCompanyCustomer,
        string $variation
    ) {
        $this->setConfigValues($salesPaymentMethodsConfig);
        $nonCompanyCustomer = $this->customerRepository->get('customer@example.com');
        $quote = $this->cartRepository->getForCustomer(1);

        $quote->setCustomerId($nonCompanyCustomer->getId());
        $quote->setCustomer($nonCompanyCustomer);
        $quote->collectTotals()->save();

        $paymentMethods = $this->paymentManagment->getList($quote->getId());
        $paymentMethodsCodes = $this->getPaymentMethodsCodes($paymentMethods);
        $this->assertEquals($expectedResultNonCompanyCustomer, $paymentMethodsCodes, 'Test - ' . $variation);

        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $companyPaymentMethodConfig['company_id'] = $company->getId();

        $companyPaymentSettings = $this->companyPaymentMethodFactory->create()->addData(
            $companyPaymentMethodConfig
        );

        $companyPaymentSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();

        $paymentMethods = $this->paymentManagment->getList($quote->getId());
        $paymentMethodsCodes = $this->getPaymentMethodsCodes($paymentMethods);
        $this->assertEquals($expectedResultCompanyCustomer, $paymentMethodsCodes, 'Test - ' . $variation);
    }

    /**
     * Test storefront payments for company/non company customers with purchase order enabled
     *
     * @param array $salesPaymentMethodsConfig
     * @param array $companyPaymentMethodConfig
     * @param array $expectedResultCompanyCustomer
     * @param array $expectedResultNonCompanyCustomer
     * @param string $variation
     *
     * @dataProvider companyPaymentMethodsConfigProvider
     */
    private function performTestCompanyStoreFrontPaymentMethodsWithPurchaseOrderEnabled(
        array $salesPaymentMethodsConfig,
        array $companyPaymentMethodConfig,
        array $expectedResultCompanyCustomer,
        array $expectedResultNonCompanyCustomer,
        string $variation
    ) {
        $salesPaymentMethodsConfig[ScopeConfigInterface::SCOPE_TYPE_DEFAULT]
        ['default']['btob/order_approval/purchaseorder_active'] = 1;

        $this->setConfigValues($salesPaymentMethodsConfig);

        $nonCompanyCustomer = $this->customerRepository->get('customer@example.com');
        $quote = $this->cartRepository->getForCustomer(1);

        $quote->setCustomerId($nonCompanyCustomer->getId());
        $quote->setCustomer($nonCompanyCustomer);
        $quote->collectTotals()->save();

        $paymentMethods = $this->paymentManagment->getList($quote->getId());
        $paymentMethodsCodes = $this->getPaymentMethodsCodes($paymentMethods);
        $this->assertEquals($expectedResultNonCompanyCustomer, $paymentMethodsCodes);

        $companyCustomer = $this->customerRepository->get('alex.smith@example.com');
        $company = $this->companyRepository->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $purchaseOrderConfig = $this->purchaseOrderConfigRepository->get($company->getId());
        $purchaseOrderConfig->setIsPurchaseOrderEnabled(true);
        $this->purchaseOrderConfigRepository->save($purchaseOrderConfig);

        $companyPaymentMethodConfig['company_id'] = $company->getId();

        $companyPaymentSettings = $this->companyPaymentMethodFactory->create()->addData(
            $companyPaymentMethodConfig
        );

        $companyPaymentSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->collectTotals()->save();

        $paymentMethods = $this->paymentManagment->getList($quote->getId());
        $paymentMethodsCodes = $this->getPaymentMethodsCodes($paymentMethods);
        $this->assertEquals($expectedResultCompanyCustomer, $paymentMethodsCodes, 'Test - ' . $variation);
    }

    /**
     * Payment methods data variations
     *
     * @return array[]
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function companyPaymentMethodsTestDataVariations()
    {
        return [
            'companyPaymentMethodsB2BAllPaymentMethods' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 1,
                            'payment/purchaseorder/active' => 1,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 0,
                    'use_config_settings' => 1
                ],
                'expectedResultCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsB2BSelectedPaymentMethods' => [
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
                    'checkmo'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsAllEnabledPaymentMethodsB2BSelectedPaymentMethods' => [
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
                    'applicable_payment_method' => 1,
                    'use_config_settings' => 0
                ],
                'expectedResultCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsAllEnabledPaymentMethodsB2BAllPaymentMethods' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 1,
                            'payment/purchaseorder/active' => 1,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0,
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 1,
                    'use_config_settings' => 0
                ],
                'expectedResultCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsSelectedPaymentMethodsB2BAllPaymentMethods' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 1,
                            'payment/purchaseorder/active' => 1,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0,
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 2,
                    'available_payment_methods' => 'purchaseorder',
                    'use_config_settings' => 0
                ],
                'expectedResultCompanyCustomer' => [
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsSelectedPaymentMethodsB2BSelectedPaymentMethods' => [
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
                    'applicable_payment_method' => 2,
                    'available_payment_methods' => 'purchaseorder',
                    'use_config_settings' => 0
                ],
                'expectedResultCompanyCustomer' => [
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsSelectedPaymentMethodsB2BSelectedPaymentMethodsAllMethodsConfigDisabled' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 0,
                            'payment/purchaseorder/active' => 0,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 1,
                            'btob/default_b2b_payment_methods/available_payment_methods' => 'checkmo'
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 2,
                    'available_payment_methods' => 'purchaseorder',
                    'use_config_settings' => 0
                ],
                'expectedResultCompanyCustomer' => [],
                'expectedResultNonCompanyCustomer' => []
            ],
            'companyPaymentMethodsSelectedPaymentMethodsB2BSelectedPaymentMethodsWebSiteScope' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 0,
                            'payment/purchaseorder/active' => 0,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 1,
                            'btob/default_b2b_payment_methods/available_payment_methods' => 'checkmo'
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
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
                    'applicable_payment_method' => 2,
                    'available_payment_methods' => 'purchaseorder',
                    'use_config_settings' => 0
                ],
                'expectedResultCompanyCustomer' => [
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsB2BAllPaymentMethodsConfigDisabledWebsiteScope' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 1,
                            'payment/purchaseorder/active' => 1,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
                            'payment/checkmo/active' => 0,
                            'payment/purchaseorder/active' => 0,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 0,
                    'use_config_settings' => 1
                ],
                'expectedResultCompanyCustomer' => [],
                'expectedResultNonCompanyCustomer' => []
            ],
            'companyPaymentMethodsB2BAllPaymentMethodsConfigEnabledWebsiteScope' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 0,
                            'payment/purchaseorder/active' => 0,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ],
                    ScopeInterface::SCOPE_WEBSITES => [
                        'base' => [
                            'payment/checkmo/active' => 1,
                            'payment/purchaseorder/active' => 1,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 0,
                    'use_config_settings' => 1
                ],
                'expectedResultCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'checkmo',
                    'purchaseorder'
                ]
            ],
            'companyPaymentMethodsB2BAllPaymentMethodsConfigUsaCountryNotAllowed' => [
                'salesPaymentMethodsConfig' => [
                    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
                        'default' => [
                            'payment/checkmo/active' => 1,
                            'payment/checkmo/allowspecific' => 1,
                            'payment/checkmo/specificcountry' => 'GB,UY',
                            'payment/purchaseorder/active' => 1,
                            'payment/fake_vault/active' => 0,
                            'payment/fake/active' => 0,
                            'btob/default_b2b_payment_methods/applicable_payment_methods' => 0
                        ]
                    ]
                ],
                'companyPaymentMethodConfig' => [
                    'applicable_payment_method' => 0,
                    'use_config_settings' => 1
                ],
                'expectedResultCompanyCustomer' => [
                    'purchaseorder'
                ],
                'expectedResultNonCompanyCustomer' => [
                    'purchaseorder'
                ]
            ],
        ];
    }

    /**
     * Get payment methods codes array
     *
     * @param array $paymentMethods
     * @return array
     */
    private function getPaymentMethodsCodes(array $paymentMethods)
    {
        $paymentCodes = [];
        foreach ($paymentMethods as $paymentMethod) {
            $paymentCodes[] = $paymentMethod->getCode();
        }
        return $paymentCodes;
    }

    /**
     * Update scope config settings
     *
     * @param array $configData
     * @return $this
     * @throws \Exception
     */
    private function setConfigValues(array $configData)
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
        return $this;
    }
}
