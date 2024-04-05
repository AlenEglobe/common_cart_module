<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\PurchaseOrder\Plugin\Checkout\Model;

use Magento\Checkout\Block\Onepage;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyPoConfigRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Test class for checkout config provider plugin
 *
 * @see \Magento\PurchaseOrder\Plugin\Checkout\Model\ConfigProvider
 *
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ConfigProviderTest extends TestCase
{
    /**
     * @var Onepage
     */
    private $onepageBlock;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CompanyPoConfigRepositoryInterface
     */
    private $companyPoConfigRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = ObjectManager::getInstance();
        $this->onepageBlock = $objectManager->get(Onepage::class);
        $this->checkoutSession = $objectManager->get(CheckoutSession::class);
        $this->customerSession = $objectManager->get(CustomerSession::class);
        $this->customerRepository = $objectManager->get(CustomerRepository::class);
        $this->quoteRepository = $objectManager->get(QuoteRepository::class);
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->companyPoConfigRepository = $objectManager->get(CompanyPoConfigRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

        // Enable company functionality at the website level
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality at the website level
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);
    }

    /**
     * Enable/Disable the configuration at the website level.
     *
     * magentoConfigFixture does not allow changing the value for website scope.
     *
     * @param string $path
     * @param bool $isEnabled
     */
    private function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = ObjectManager::getInstance()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            $path,
            $isEnabled ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Get a company by name.
     *
     * @param string $companyName
     * @return CompanyInterface
     */
    private function getCompanyByName(string $companyName)
    {
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        return $company;
    }

    /**
     * Enable/Disable purchase order functionality for the provided company.
     *
     * @param CompanyInterface $company
     * @param bool $isEnabled
     */
    private function setCompanyPurchaseOrderConfig(CompanyInterface $company, bool $isEnabled)
    {
        $companyConfig = $this->companyPoConfigRepository->get($company->getId());
        $companyConfig->setIsPurchaseOrderEnabled($isEnabled);

        $this->companyPoConfigRepository->save($companyConfig);
    }

    /**
     * Set the payment configuration for the provided company to use the default B2B payment configuration.
     *
     * @param CompanyInterface $company
     */
    private function setCompanyPaymentConfigToDefaultB2b(CompanyInterface $company)
    {
        $extensionAttributes = $company->getExtensionAttributes();
        $extensionAttributes->setApplicablePaymentMethod(0);
        $company->setExtensionAttributes($extensionAttributes);

        $this->companyRepository->save($company);
    }

    /**
     * Assigns the provided customer to the quote with the provided reserveOrderId.
     *
     * @param CustomerInterface $customer
     * @param string $reserveOrderId
     */
    private function assignCustomerToQuote(CustomerInterface $customer, string $reserveOrderId): void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('reserved_order_id', $reserveOrderId)
            ->create();
        $items = $this->quoteRepository->getList($searchCriteria)->getItems();

        $quote = reset($items);
        $quote->setCustomer($customer);
        $this->quoteRepository->save($quote);
    }

    /**
     * Test that the Onepage checkout config includes payment method information when purchase orders are enabled.
     *
     * This should include information indicating whether the payment is offline.
     *
     * @magentoConfigFixture current_store payment/checkmo/active 1
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/fake/active 0
     * @magentoConfigFixture current_store payment/fake_vault/active 0
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_simple_product_saved.php
     * @dataProvider expectedPaymentMethodsDataProvider
     * @param array $expectedPaymentMethods
     */
    public function testAfterGetCheckoutConfigAddsPaymentMethodInformation(array $expectedPaymentMethods)
    {
        // Configure the company to use purchase orders and the default B2B payment methods
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPaymentConfigToDefaultB2b($company);
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Assign the customer to the quote from the fixture and load it in the checkout session.
        $customer = $this->customerRepository->get('Alex.Smith@example.com');
        $this->assignCustomerToQuote($customer, 'test_order_with_simple_product_without_address');
        $this->customerSession->loginById($customer->getId());
        $this->checkoutSession->loadCustomerQuote();

        // Get the actual payment methods from the checkout config.
        $checkoutConfig = $this->onepageBlock->getCheckoutConfig();
        $actualPaymentMethods = $checkoutConfig['paymentMethods'];
        usort($actualPaymentMethods, function ($firstItem, $secondItem) {
            return $firstItem['code'] <=> $secondItem['code'];
        });

        // Assert that all available payment methods for the company are present in the config with the expected data
        $this->assertEquals($expectedPaymentMethods, $actualPaymentMethods);
    }

    /**
     * Expected payment methods data provider.
     *
     * @return array
     */
    public function expectedPaymentMethodsDataProvider()
    {
        return [
            [
                'expectedPaymentMethods' => [
                    [
                        'code' => 'checkmo',
                        'title' => 'Check / Money order',
                        'is_deferred' => false
                    ],
                    [
                        'code' => 'paypal_express',
                        'title' => 'PayPal Express Checkout',
                        'is_deferred' => true
                    ],
                    [
                        'code' => 'paypal_express_bml',
                        'title' => 'PayPal Credit (Paypal Express Bml)',
                        'is_deferred' => true
                    ]
                ]
            ]
        ];
    }
}
