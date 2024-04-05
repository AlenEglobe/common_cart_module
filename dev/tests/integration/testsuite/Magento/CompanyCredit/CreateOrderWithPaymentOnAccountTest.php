<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyCredit;

use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\CompanyCredit\Model\CreditLimitManagement;
use Magento\CompanyPayment\Model\CompanyPaymentMethod;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\Constraint\StringContains;

/**
 * Test create admin order using payment on account.
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreateOrderWithPaymentOnAccountTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var string
     */
    protected $resource = 'Magento_Sales::create';

    /**
     * @var string
     */
    protected $uri = 'backend/sales/order_create/index';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var MutableScopeConfigInterface
     */
    private $mutableScopeConfig;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->mutableScopeConfig = $this->objectManager->get(MutableScopeConfigInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
    }

    /**
     * Check that currency symbol updates correctly based on currency symbol configuration
     *
     * @magentoConfigFixture current_store currency/options/customsymbol {"USD":"@"}
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderCurrencySymbolUpdatesCorrectly()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 1]]);
        $companyCreditForm = $this->objectManager->create(\Magento\CompanyCredit\Block\Form\CompanyCredit::class);

        // reset static currency cache to empty array
        $currencyCacheReflectionProp = new \ReflectionProperty(
            \Magento\Framework\Locale\Currency::class,
            '_currencyCache'
        );
        $currencyCacheReflectionProp->setAccessible(true);
        $currencyCacheReflectionProp->setValue(null, []);

        $this->assertStringContainsString("@", $companyCreditForm->getCurrentCustomerCreditBalance());

        // reset static currency cache to empty array
        $currencyCacheReflectionProp = new \ReflectionProperty(
            \Magento\Framework\Locale\Currency::class,
            '_currencyCache'
        );
        $currencyCacheReflectionProp->setAccessible(true);
        $currencyCacheReflectionProp->setValue(null, []);
    }

    /**
     * Test admin cannot place order with Payment on Account when exceeds available credit
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountAndExceedsAvailableCredit()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 10]]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringContainsString('Payment on Account', $this->getResponse()->getBody());
        $this->assertStringContainsString(
            "$('input#p_method_companycredit').attr('disabled', 'disabled')",
            $this->getResponse()->getBody()
        );
    }

    /**
     * Test admin can place order with Payment on Account when exceeds available credit
     * & company has the configuration enabled to exceed available credit
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountAndExceedsAvailableCreditButEnableAllowExceedAvilableCredit()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $company = $this->getCompanyCreatedByFixture();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 10]]);
        $creditLimitManagement = $this->objectManager->get(CreditLimitManagement::class);
        $creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
        $creditLimit->setExceedLimit(true);
        $creditLimit->save();
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringContainsString('Payment on Account', $this->getResponse()->getBody());
        $this->assertStringNotContainsString(
            "$('input#p_method_companycredit').attr('disabled', 'disabled')",
            $this->getResponse()->getBody()
        );
    }

    /**
     * Test admin cannot place order with Payment on Account when disabled on store payment methods
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 0
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenDisabledOnStorePaymentMethods()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $company = $this->getCompanyCreatedByFixture();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 10]]);
        $creditLimitManagement = $this->objectManager->get(CreditLimitManagement::class);
        $creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
        $creditLimit->setExceedLimit(true);
        $creditLimit->save();
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString("Payment on Account", $this->getResponse()->getBody());
    }

    /**
     * Test admin cannot place order with Payment on Account when disabled on website payment methods
     *
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     * @magentoDataFixture Magento/Catalog/_files/product_two_websites.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenDisabledOnWebsitePaymentMethods()
    {
        $this->setConfig(true, true, ScopeInterface::SCOPE_WEBSITE, 'test');

        $customer = $this->customerRepository->get('email@companyquote.com');

        /** @var WebsiteRepositoryInterface $websiteRepository */
        $websiteRepository = $this->objectManager->create(WebsiteRepositoryInterface::class);
        $website = $websiteRepository->get('test');

        /** @var StoreRepositoryInterface $storeRepository */
        $storeRepository = $this->objectManager->create(StoreRepositoryInterface::class);
        $store = $storeRepository->get('fixture_second_store');

        $customer
            ->setWebsiteId($website->getId())
            ->setStoreId($store->getId());

        $this->customerRepository->save($customer);

        /** @var CartRepositoryInterface $quoteRepository */
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);

        $quote = $quoteRepository->getActiveForCustomer($customer->getId());

        $quote->setStoreId($store->getId());
        $quote->setWebsite($website);
        $product = $this->productRepository->get('simple-on-two-websites');
        $quote->addProduct($product);
        $quoteRepository->save($quote);

        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());

        /** @var \Magento\Sales\Model\AdminOrder\Create $order */
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->getSession()
            ->setStoreId($store->getId())
            ->setQuoteId($quote->getId())
            ->setCustomerId($customer->getId());

        $order->addProducts([$product->getId() => ['qty' => 10]]);

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->getRequest()->setPostValue([
            'customerId' => $customer->getId(),
            'customer_id' => $customer->getId(),
            'storeId' => $store->getId(),
            'store_id' => $store->getId(),
            'quote_id' => $quote->getId(),
            'json' => 1,
        ]);

        $this->dispatch('backend/sales/order_create/loadBlock/block/billing_method');

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringNotContainsString('Payment on Account', $this->getResponse()->getBody());
        $this->resetConfig();
    }

    /**
     * Test admin cannot place order with Payment on Account when disabled on B2B payment methods
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/available_payment_methods null
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenDisabledOnB2BPaymentMethods()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 10]]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString("Payment on Account", $this->getResponse()->getBody());
    }

    /**
     * Test admin cannot place order with Payment on Account when disabled on company payment methods
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenDisabledOnCompanyPaymentMethods()
    {
        $this->setConfig(true, true, ScopeInterface::SCOPE_WEBSITE, 'test');
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $company = $this->getCompanyCreatedByFixture();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 1]]);

        /** @var CompanyPaymentMethod $companyPaymentMethodModel */
        $companyPaymentMethodModel = $this->objectManager->get(
            CompanyPaymentMethod::class
        );
        $companyPaymentMethod = $companyPaymentMethodModel->load($company->getId());
        $companyPaymentMethod->setCompanyId($company->getId());
        $companyPaymentMethod->setApplicablePaymentMethod(1);
        $companyPaymentMethod->setAvailablePaymentMethods(null);
        $companyPaymentMethod->save();
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString("Payment on Account", $this->getResponse()->getBody());
        $this->resetConfig();
    }

    /**
     * Test admin cannot place order with Payment on Account when it is not allowed for specific country
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoConfigFixture current_store payment/companycredit/allowspecific 1
     * @magentoConfigFixture current_store payment/companycredit/specificcountry AT
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenNotAllowedForSpecificCountry()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $company = $this->getCompanyCreatedByFixture();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 10]]);
        $creditLimitManagement = $this->objectManager->get(CreditLimitManagement::class);
        $creditLimit = $creditLimitManagement->getCreditByCompanyId($company->getId());
        $creditLimit->setExceedLimit(true);
        $creditLimit->save();
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString('Payment on Account', $this->getResponse()->getBody());
    }

    /**
     * Test admin cannot place order with Payment on Account when order is less than Minimum Order Total
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoConfigFixture current_store payment/companycredit/min_order_total 1000
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenOrderIsLessThanMinimumOrderTotal()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 1]]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString("Payment on Account", $this->getResponse()->getBody());
    }

    /**
     * Test admin cannot place order with Payment on Account when order is greater than Maximum Order Total
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoConfigFixture current_store payment/companycredit/max_order_total 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenOrderIsGreaterThanMaximumOrderTotal()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@companyquote.com');
        $customerId = $customer->getId();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 1]]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString("Payment on Account", $this->getResponse()->getBody());
    }

    /**
     * Test admin cannot place order with Payment on Account when customer is not in a company
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/quote_with_non_company_customer.php
     */
    public function testCreateOrderWithPaymentOnAccountWhenCustomerIsNotInCompany()
    {
        $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->get('email@nocompanyquote.com');
        $customerId = $customer->getId();
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $product = $this->productRepository->get('simple');
        $order = $this->objectManager->get(\Magento\Sales\Model\AdminOrder\Create::class);
        $order->setCustomerId($customerId);
        $order->addProducts([$product->getId() => ['qty' => 1]]);
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setParam('customer_id', $customerId);
        $this->getRequest()->setParam('block', 'billing_method');
        $this->getRequest()->setParam('json', 1);
        $this->dispatch('backend/sales/order_create/loadBlock');
        $this->assertStringNotContainsString("Payment on Account", $this->getResponse()->getBody());
    }

    /**
     * Check that new order is created with the order status that
     * is set in store configuration for payment method
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountPlaceOrder()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quoteRepository = $this->quoteRepository;
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $product = $this->productRepository->get('simple');
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();
        $quoteRepository->save($quote);

        $orderData = [
            'shipping_method' => 'flatrate_flatrate',
            'billing_address' => $this->getBillingAddressPayloadFromQuote($quote),
            'account' => [
                'email' => $customer->getEmail(),
            ]
        ];
        $paymentData = [
            'method' => 'companycredit',
            'po_number' => '234445'
        ];
        $itemData = [
            $product->getId() => [
                'qty'  => 1,
                'use_discount' => 1
            ]
        ];

        $this->getRequest()->setPostValue(
            [
                'order' => $orderData,
                'payment' => $paymentData,
                'item' => $itemData,
                'shipping_same_as_billing' => 'on',
            ]
        );

        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $session->setCustomerId($customer->getId());

        $companyId = $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();

        /** @var CreditLimitManagement $creditLimitManagement */
        $creditLimitManagement = $this->objectManager->get(CreditLimitManagement::class);

        $creditLimit = $creditLimitManagement->getCreditByCompanyId($companyId);
        $creditLimit->setExceedLimit(false);
        $creditLimit->setCreditLimit(1000);
        $initialCreditBalance = $creditLimit->getBalance();
        $creditLimit->save();

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/sales/order_create/save');

        $this->assertSessionMessages(
            $this->equalTo([(string)__('You created the order.')]),
            MessageInterface::TYPE_SUCCESS
        );

        // get updated credit limit/quote from database
        $newCreditLimit = $creditLimitManagement->getCreditByCompanyId($companyId);
        $newQuote = $quoteRepository->get($quote->getId());

        $order = $this->getOrderByQuote($newQuote);

        // assert credit limit has decreased by amount of order's grand total
        $this->assertEquals($initialCreditBalance - $order->getGrandTotal(), $newCreditLimit->getBalance());
    }

    /**
     * Check that custom reference number is included in email when set in Payment on Account order
     *
     * @magentoConfigFixture default/btob/website_configuration/company_active 1
     * @magentoConfigFixture current_store payment/companycredit/active 1
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/CompanyCredit/_files/company_with_quote_and_credit_limit.php
     */
    public function testCreateOrderWithPaymentOnAccountPlaceOrderCheckCustomReferenceNumber()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quoteRepository = $this->quoteRepository;
        $quote = $quoteRepository->getActiveForCustomer($customer->getId());
        $product = $this->productRepository->get('simple');
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();
        $quoteRepository->save($quote);

        $orderData = [
            'shipping_method' => 'flatrate_flatrate',
            'billing_address' => $this->getBillingAddressPayloadFromQuote($quote),
            'send_confirmation' => true,
            'account' => [
                'email' => $customer->getEmail(),
            ]
        ];
        $paymentData = [
            'method' => 'companycredit',
            'po_number' => '234445'
        ];
        $itemData = [
            $product->getId() => [
                'qty'  => 1,
                'use_discount' => 1
            ]
        ];

        $this->getRequest()->setPostValue(
            [
                'order' => $orderData,
                'payment' => $paymentData,
                'item' => $itemData,
                'shipping_same_as_billing' => 'on',
            ]
        );

        $session = $this->objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $session->setCustomerId($customer->getId());

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/sales/order_create/save');

        $this->assertSessionMessages(
            $this->equalTo([(string)__('You created the order.')]),
            MessageInterface::TYPE_SUCCESS
        );

        $transportBuilder = $this->objectManager->get(TransportBuilderMock::class);
        $message = $transportBuilder->getSentMessage();

        // assert custom reference number is included in email

        $assert = $this->logicalAnd(
            new StringContains($paymentData["po_number"]),
            new StringContains('Payment on Account')
        );

        $this->assertThat($message->getBody()->getParts()[0]->getRawContent(), $assert);
    }

    /**
     * Set Company/ComoanyCredit configuration
     *
     * @param bool $companyActive
     * @param bool $companyCreditActive
     * @param string $companyCreditActiveScope
     * @param string|null $scopeCode
     */
    private function setConfig(
        bool $companyActive,
        bool $companyCreditActive,
        string $companyCreditActiveScope,
        ?string $scopeCode = null
    ): void {
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/company_active',
            $companyActive,
            ScopeInterface::SCOPE_STORE
        );

        $this->mutableScopeConfig->setValue(
            'payment/companycredit/active',
            $companyCreditActive,
            $companyCreditActiveScope,
            $scopeCode
        );
    }

    /**
     * @return void
     */
    private function resetConfig(): void
    {
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/company_active',
            null,
            ScopeInterface::SCOPE_STORE
        );

        $this->mutableScopeConfig->setValue(
            'payment/companycredit/active',
            null,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Gets the company created by the data fixture.
     *
     * @return CompanyInterface
     */
    private function getCompanyCreatedByFixture()
    {
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilter('company_name', 'company quote');
        $searchCriteria = $searchCriteriaBuilder->create();

        $companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $companySearchResults = $companyRepository->getList($searchCriteria);
        $items = $companySearchResults->getItems();

        /** @var CompanyInterface $company */
        $company = reset($items);

        return $company;
    }

    /**
     * Get raw billing address payload to POST to backend order creation endpoint
     *
     * @var CartInterface $quote
     * @return array
     */
    private function getBillingAddressPayloadFromQuote(CartInterface $quote)
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'customer_address_id' => $billingAddress->getCustomerAddressId(),
            'firstname' => $billingAddress->getFirstname(),
            'lastname' => $billingAddress->getLastname(),
            'company' => $billingAddress->getCompany(),
            'street' => $billingAddress->getStreet(),
            'city' => $billingAddress->getCity(),
            'region_id' => $billingAddress->getRegionId(),
            'region' => $billingAddress->getRegion(),
            'region_code' => $billingAddress->getRegionCode(),
            'postcode' => $billingAddress->getPostcode(),
            'country_id' => $billingAddress->getCountryId(),
            'telephone' => $billingAddress->getTelephone(),
            'fax' => $billingAddress->getFax(),
        ];
    }

    /**
     * @param CartInterface $quote
     * @return OrderInterface
     */
    private function getOrderByQuote(CartInterface $quote)
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->objectManager->create(FilterBuilder::class);
        $filters = [
            $filterBuilder->setField(OrderInterface::QUOTE_ID)
                ->setValue($quote->getId())
                ->create()
        ];

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilters($filters)
            ->create();

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $orders = $orderRepository->getList($searchCriteria)
            ->getItems();

        /** @var OrderInterface $order */
        $order = array_pop($orders);

        return $order;
    }
}
