<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Service\V1;

use Exception;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Config\Model\ResourceModel\Config as CoreConfigResourceModel;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Model\HistoryManagementInterface as NegotiableQuoteHistoryManagementInterface;
use Magento\PurchaseOrder\Model\Company\Config\Repository as PurchaseOrderCompanyConfigRepository;
use Magento\PurchaseOrder\Model\PurchaseOrderManagement;
use Magento\PurchaseOrder\Model\PurchaseOrderPaymentInformationManagement;
use Magento\PurchaseOrder\Model\PurchaseOrderRepository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface as StoreScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use SoapFault;
use Magento\Framework\App\ResourceConnection as AppResource;
use Magento\Framework\DB\Ddl\Sequence as DdlSequence;

/**
 * Test for saving payment information and creating purchase order
 * @see PurchaseOrderPaymentInformationManagement
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class PurchaseOrderPaymentInformationManagementTest extends WebapiAbstract
{
    /**
     * API Services configuration
     */
    private const SERVICE_VERSION = 'V1';
    private const SERVICE_NAME = 'purchaseOrderPurchaseOrderPaymentInformationManagementV1';
    private const RESOURCE_PATH = '/V1/carts/mine/po-payment-information';
    private const TOTALS_SERVICE_NAME = 'quoteCartTotalRepositoryV1';
    private const TOTALS_METHOD_NAME = 'get';
    private const TOTALS_RESOURCE_PATH = '/V1/purchase-order-carts/:cartId/totals';
    private const BILLING_ADDRESS_SERVICE_NAME = 'quoteBillingAddressManagementV1';
    private const BILLING_ADDRESS_METHOD_NAME = 'assign';
    private const BILLING_ADDRESS_RESOURCE_PATH = '/V1/purchase-order-carts/:cartId/billing-address';

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var PurchaseOrderRepository
     */
    private $purchaseOrderRepository;

    /**
     * @var PurchaseOrderCompanyConfigRepository
     */
    private $purchaseOrderCompanyConfigRepository;

    /**
     * @var CoreConfigResourceModel
     */
    private $coreConfigResourceModel;

    /**
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;

    /**
     * @var NegotiableQuoteHistoryManagementInterface
     */
    private $negotiableQuoteHistoryManagement;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PurchaseOrderManagement
     */
    private $purchaseOrderManagement;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var AppResource
     */
    private $appResource;

    /**
     * @var DdlSequence
     */
    private $ddlSequence;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->customerRegistry = $this->objectManager->get(CustomerRegistry::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->filterBuilder = $this->objectManager->get(FilterBuilder::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->customerTokenService = $this->objectManager->get(CustomerTokenServiceInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepository::class);
        $this->coreConfigResourceModel = $this->objectManager->get(CoreConfigResourceModel::class);
        $this->reinitableConfig = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->purchaseOrderCompanyConfigRepository = $this->objectManager->get(
            PurchaseOrderCompanyConfigRepository::class
        );
        $this->negotiableQuoteHistoryManagement = $this->objectManager->get(
            NegotiableQuoteHistoryManagementInterface::class
        );
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $this->purchaseOrderManagement = $this->objectManager->get(PurchaseOrderManagement::class);
        $this->serializer = $this->objectManager->get(Json::class);
        $this->ddlSequence = $this->objectManager->get(DdlSequence::class);
        $this->appResource = $this->objectManager->get(AppResource::class);
    }

    /**
     * Disable PurchaseOrder/Company functionality
     */
    protected function tearDown(): void
    {
        // enable purchase order/company functionality
        $this->setB2BFeaturesCompanyActiveStatus(false, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(false, StoreScopeInterface::SCOPE_WEBSITE);

        $this->setPurchaseOrderEnabledStatus(false, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        parent::tearDown();
    }

    /**
     * Test initial purchase order taxes based on billing address calculation
     *
     * @param string $customerEmail
     * @param int $expectedTaxAmountUS
     * @param int $expectedTaxAmountCA
     * @param array $billingAddressPayload
     *
     * @dataProvider taxBillingAddressBasedDataProvider
     * @magentoConfigFixture default_store tax/calculation/based_on billing
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/purchaseorder_enabled 1
     * @magentoApiDataFixture Magento/PurchaseOrder/_files/quote_with_address_tax.php
     * @magentoAppIsolation enabled
     *
     * @throws AuthenticationException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testTaxBillingAddressBased(
        string $customerEmail,
        int $expectedTaxAmountUS,
        int $expectedTaxAmountCA,
        array $billingAddressPayload
    ) {
        // Get the customer creating the negotiable quote
        $customer = $this->customerRepository->get($customerEmail);
        $this->setCorrectPasswordHashOnCreatedCustomer($customer);
        $token = $this->getToken($customerEmail, 'password');

        // Enable Company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        // Enable Purchase Order functionality
        $this->setPurchaseOrderEnabledStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setPurchaseOrderEnabledStatusForCustomer($customer, true);

        $this->reinitableConfig->reinit();

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');
        // assign customer to quote
        $quote->setCustomer($customer);

        $initialTotalsResult = $this->_webApiCall(
            $this->getServiceInfoTotals($quote->getId()),
            [
                'cart_id' => $quote->getId()
            ]
        );

        $this->assertEquals($expectedTaxAmountUS, $initialTotalsResult['tax_amount']);

        $this->_webApiCall(
            $this->getServiceInfoBillingAddress($quote->getId()),
            [
                'cart_id' => $quote->getId(),
                'address' => $billingAddressPayload
            ]
        );

        $updatedTotalsResult = $this->_webApiCall(
            $this->getServiceInfoTotals($quote->getId()),
            [
                'cart_id' => $quote->getId()
            ]
        );

        $this->assertEquals($expectedTaxAmountCA, $updatedTotalsResult['tax_amount']);

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');

        // Make WebAPI call to create the Purchase Order
        $purchaseOrderId = $this->_webApiCall(
            $this->getServiceInfo($token),
            [
                'cart_id' => $quote->getId(),
                'billingAddress' => $billingAddressPayload,
                'paymentMethod' => [
                    'method' => 'checkmo',
                    'po_number' => null,
                    'additional_data' => null,
                ]
            ]
        );

        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);

        $this->assertEquals($quote->getGrandTotal(), $purchaseOrder->getGrandTotal());

        $snapshotQuoteTotals = $purchaseOrder->getSnapshotQuote()->getTotals();

        $this->assertEquals($expectedTaxAmountCA, $snapshotQuoteTotals['tax']->getValue());
    }

    /**
     * Given a company admin that is authenticated with API
     * And Purchase Order is enabled for that company
     * When they place an API call to create Purchase Order with active quote
     * Then a purchase order is successfully created
     *
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     * @magentoApiDataFixture Magento/Sales/_files/address.php
     * @magentoApiDataFixture Magento/Checkout/_files/simple_product.php
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture default_store btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/purchaseorder_enabled 1
     * @magentoConfigFixture default_store payment/checkmo/active 1
     * @magentoConfigFixture default_store payment/paypal_express/active 1
     * @dataProvider paymentMethodsDataProvider
     *
     * @param string $paymentMethodCode
     * @throws AuthenticationException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSuccessfulPlacementWithCompanyAdmin(string $paymentMethodCode)
    {
        $customer = $this->customerRepository->get('company-admin@example.com');
        $this->setCorrectPasswordHashOnCreatedCustomer($customer);

        // enable purchase order/company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        $this->setPurchaseOrderEnabledStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setPurchaseOrderEnabledStatusForCustomer($customer, true);

        $this->reinitableConfig->reinit();

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');
        // assign customer to quote
        $quote->setCustomer($customer);

        $quote->addProduct($this->productRepository->get('simple'));
        $this->assignShippingAddressToQuote($quote);

        $this->quoteRepository->save($quote);

        $token = $this->getToken(
            'company-admin@example.com',
            'password'
        );

        // Make WebAPI call
        $purchaseOrderId = $this->_webApiCall(
            $this->getServiceInfo($token),
            $this->getRequestData($quote, $paymentMethodCode)
        );

        // perform assertions on created purchase order in database
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);
        $companyId = $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        $this->assertEquals($quote->getId(), $purchaseOrder->getQuoteId());
        $this->assertEquals($customer->getId(), $purchaseOrder->getCreatorId());
        $this->assertEquals(
            $companyId,
            $purchaseOrder->getCompanyId()
        );
        $this->assertEquals($paymentMethodCode, $purchaseOrder->getPaymentMethod());
        $this->assertEquals(
            $this->getActiveQuoteByReservedOrderId('test_order_1')->collectTotals()->getGrandTotal(),
            $purchaseOrder->getGrandTotal()
        );
        $this->assertEquals(
            $quote->getId(),
            $purchaseOrder->getSnapshotQuote()->getId()
        );
    }

    /**
     * Payment methods data provider.
     *
     * @return array
     */
    public function paymentMethodsDataProvider()
    {
        return [
            'offline_payment_method' => [
                'payment_method_code' => 'checkmo'
            ] ,
            'online_payment_method' => [
                'payment_method_code' => 'paypal_express'
            ]
        ];
    }

    /**
     * Given a company admin in second website that is authenticated with API
     * And Purchase Order is enabled for that company
     * When they place an API call to create a Purchase Order with product in second website
     * Then a Purchase Order is successfully created
     *
     * Given the product's price is changed
     * When the Purchase Order is converted to an Order
     * Then the Order still has the same grand total as the Purchase Order's
     *
     * @magentoApiDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoApiDataFixture Magento/Store/_files/second_store_with_second_currency.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote_customer_not_default_store.php
     * @magentoApiDataFixture Magento/Sales/_files/address.php
     * @magentoApiDataFixture Magento/Checkout/_files/simple_product.php
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/purchaseorder_enabled 1
     *
     * @throws AuthenticationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSuccessfulPlacementAndConversionToOrderInSecondStore()
    {
        $customer = $this->customerRepository->get('company-admin@example.com');
        $this->setCorrectPasswordHashOnCreatedCustomer($customer);

        // enable purchase order/company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        $this->setPurchaseOrderEnabledStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setPurchaseOrderEnabledStatusForCustomer($customer, true);

        $secondStore = $this->storeManager->getStore('fixture_second_store');
        $this->initSequence([$secondStore->getId()]);
        $this->storeManager->setCurrentStore($secondStore);
        $secondWebsiteId = $secondStore->getWebsiteId();
        $secondWebsite = $this->storeManager->getWebsite($secondWebsiteId);
        $customer->setWebsiteId($secondWebsiteId);

        $this->customerRepository->save($customer);

        $this->reinitableConfig->reinit();

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1_not_default_store');

        // assign customer to quote
        $quote->setCustomer($customer);

        // assign product to second website
        $product = $this->productRepository->get('simple');
        $product->setWebsiteIds([$secondWebsiteId]);
        $this->productRepository->save($product);

        // assign quote to second website
        $quote->setWebsite($secondWebsite);

        // assign product and shipping address to quote
        $quote->addProduct($product);
        $this->assignShippingAddressToQuote($quote);

        $this->quoteRepository->save($quote);

        $token = $this->getToken(
            'company-admin@example.com',
            'password'
        );

        // Make WebAPI call
        $purchaseOrderId = $this->_webApiCall(
            $this->getServiceInfo($token),
            $this->getRequestData($quote)
        );

        // perform assertions on created purchase order in database
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);
        $companyId = $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        $this->assertEquals($quote->getId(), $purchaseOrder->getQuoteId());
        $this->assertEquals($customer->getId(), $purchaseOrder->getCreatorId());
        $this->assertEquals(
            $companyId,
            $purchaseOrder->getCompanyId()
        );
        $this->assertEquals('checkmo', $purchaseOrder->getPaymentMethod());

        // fetch fresh quote from database
        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1_not_default_store');

        $this->assertEquals(
            $quote->getGrandTotal(),
            $purchaseOrder->getGrandTotal()
        );

        // change price on product
        $product->setPrice($product->getPrice() * 10);
        $this->productRepository->save($product);

        // create sales order from purchase order
        $this->purchaseOrderManagement->createSalesOrder($purchaseOrder);

        // fetch fresh purchase order from database
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);
        $salesOrder = $this->orderRepository->get($purchaseOrder->getOrderId());

        // assert grand total from order is same as purchase order's
        $this->assertEquals(
            $purchaseOrder->getGrandTotal(),
            $salesOrder->getGrandTotal()
        );
    }

    /**
     * Given a company admin that is authenticated with API
     * And Purchase Order is enabled for that company
     * When they place an API call to create Purchase Order with an active negotiable quote
     * Then a purchase order is successfully created
     * And the associated quote contains the negotiated rate
     *
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     * @magentoApiDataFixture Magento/Sales/_files/address.php
     * @magentoApiDataFixture Magento/Checkout/_files/simple_product.php
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/purchaseorder_enabled 1
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active 1
     *
     * @throws AuthenticationException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSuccessfulPlacementWithNegotiableQuote()
    {
        // Get the customer creating the negotiable quote
        $customer = $this->customerRepository->get('company-admin@example.com');
        $this->setCorrectPasswordHashOnCreatedCustomer($customer);
        $token = $this->getToken('company-admin@example.com', 'password');

        // Enable Company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        // Enable Purchase Order functionality
        $this->setPurchaseOrderEnabledStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setPurchaseOrderEnabledStatusForCustomer($customer, true);

        $this->reinitableConfig->reinit();

        // Prepare the quote so that it is valid for checkout
        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');
        $quote->setCustomer($customer);
        $this->assignShippingAddressToQuote($quote);
        $quote->addProduct($this->productRepository->get('simple'));
        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        // Create a negotiable quote and apply a discount
        $this->createNegotiableQuoteFromQuote($quote);

        // Make WebAPI call to create the Purchase Order
        $purchaseOrderId = $this->_webApiCall(
            $this->getServiceInfo($token),
            $this->getRequestData($quote)
        );

        // Perform assertions on created purchase order in database
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);
        $companyId = $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        $this->assertEquals($quote->getId(), $purchaseOrder->getQuoteId());
        $this->assertEquals($customer->getId(), $purchaseOrder->getCreatorId());
        $this->assertEquals($companyId, $purchaseOrder->getCompanyId());
        $this->assertEquals('checkmo', $purchaseOrder->getPaymentMethod());
        $this->assertEquals(
            $quote->getId(),
            $purchaseOrder->getSnapshotQuote()->getId()
        );
        $this->assertEquals(
            14.00, // $9 item (including 10 percent discount) + $5 shipping
            $purchaseOrder->getGrandTotal()
        );

        $quoteHistoryLogs = $this->negotiableQuoteHistoryManagement->getQuoteHistory($quote->getId());

        /** @var $lastQuoteHistoryLog \Magento\NegotiableQuote\Model\History */
        $lastQuoteHistoryLog = array_pop($quoteHistoryLogs);

        $logData = $this->serializer->unserialize($lastQuoteHistoryLog->getLogData());

        $this->assertArrayHasKey('status', $logData);
        $this->assertEquals(
            'Purchase Order in Progress',
            $logData['status']['new_value']
        );
    }

    /**
     * Given a non-authenticated customer
     * And Purchase Order is enabled for that company
     * When they attempt to place an API call to create Purchase Order with active quote
     * Then they are forbidden from creating a purchase order
     *
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     * @magentoApiDataFixture Magento/Checkout/_files/simple_product.php
     */
    public function testFailedPlacementWithGuest()
    {
        // enable purchase order/company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);
        $this->setPurchaseOrderEnabledStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $this->reinitableConfig->reinit();

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');
        $quote->addProduct($this->productRepository->get('simple'));
        $this->assignShippingAddressToQuote($quote);
        $this->quoteRepository->save($quote);

        try {
            $this->_webApiCall(
                $this->getServiceInfo(),
                $this->getRequestData($quote)
            );

            $this->fail('API call was successful');
        } catch (SoapFault $e) {
            $this->assertStringContainsString('The consumer isn\'t authorized to access %resources.', $e->getMessage());
        } catch (Exception $e) {
            $this->assertEquals(
                'The consumer isn\'t authorized to access %resources.',
                json_decode($e->getMessage(), true)['message']
            );
            $this->assertEquals(WebapiException::HTTP_UNAUTHORIZED, $e->getCode());
        }
    }

    /**
     * Given a B2C customer that is authenticated with API
     * And Purchase Order is enabled for that company
     * When they place an API call to create Purchase Order with active quote
     * They are forbidden from creating a purchase order
     *
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     * @magentoApiDataFixture Magento/Checkout/_files/simple_product.php
     */
    public function testFailedPlacementWithB2CCustomer()
    {
        $customer = $this->customerRepository->get('customer@example.com');

        // enable purchase order/company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);
        $this->setPurchaseOrderEnabledStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $this->reinitableConfig->reinit();

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');
        $quote->setCustomer($customer);
        $quote->addProduct($this->productRepository->get('simple'));
        $this->assignShippingAddressToQuote($quote);
        $this->quoteRepository->save($quote);

        $token = $this->getToken(
            'customer@example.com',
            'password'
        );

        try {
            $this->_webApiCall(
                $this->getServiceInfo($token),
                $this->getRequestData($quote)
            );

            $this->fail('API call was successful');
        } catch (SoapFault $e) {
            $this->assertEquals(
                'Customer is not a member of a company that has purchase orders enabled.',
                $e->getMessage()
            );
        } catch (Exception $e) {
            $this->assertEquals(
                'Customer is not a member of a company that has purchase orders enabled.',
                json_decode($e->getMessage(), true)['message']
            );
            $this->assertEquals(WebapiException::HTTP_UNAUTHORIZED, $e->getCode());
        }
    }

    /**
     * Given a company admin that is authenticated with API
     * And Purchase Order is disabled
     * When they place an API call to create Purchase Order with active quote
     * Then they are forbidden from creating a purchase order
     *
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     * @magentoApiDataFixture Magento/Sales/_files/address.php
     * @magentoApiDataFixture Magento/Checkout/_files/simple_product.php
     * @magentoApiDataFixture Magento/Company/_files/company_with_admin.php
     *
     * @throws AuthenticationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testFailedPlacementWithCompanyAdminAndPurchaseOrderDisabled()
    {
        $customer = $this->customerRepository->get('company-admin@example.com');
        $this->setCorrectPasswordHashOnCreatedCustomer($customer);

        // enable company functionality
        $this->setB2BFeaturesCompanyActiveStatus(true, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $this->setB2BFeaturesCompanyActiveStatus(true, StoreScopeInterface::SCOPE_WEBSITE);

        // disable purchase order functionality
        $this->setPurchaseOrderEnabledStatus(false, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $this->reinitableConfig->reinit();

        $quote = $this->getActiveQuoteByReservedOrderId('test_order_1');
        // assign customer to quote
        $quote->setCustomer($customer);

        $quote->addProduct($this->productRepository->get('simple'));
        $this->assignShippingAddressToQuote($quote);

        $this->quoteRepository->save($quote);

        $token = $this->getToken(
            'company-admin@example.com',
            'password'
        );

        // Make WebAPI call
        try {
            $this->_webApiCall(
                $this->getServiceInfo($token),
                $this->getRequestData($quote)
            );

            $this->fail('API call was successful');
        } catch (SoapFault $e) {
            $this->assertEquals(
                'Customer is not a member of a company that has purchase orders enabled.',
                $e->getMessage()
            );
        } catch (Exception $e) {
            $this->assertEquals(
                'Customer is not a member of a company that has purchase orders enabled.',
                json_decode($e->getMessage(), true)['message']
            );
            $this->assertEquals(WebapiException::HTTP_UNAUTHORIZED, $e->getCode());
        }
    }

    /**
     * Data provider for taxes test based on billing address
     *
     * @return array[]
     */
    public function taxBillingAddressBasedDataProvider()
    {
        return [
            'default' => [
                'customerEmail' => 'customer@example.com',
                'expectedTaxAmountUS' => 4,
                'expectedTaxAmountCA' => 10,
                'billingAddressPayload' => [
                    'firstname' => 'John',
                    'lastname' => 'Smith',
                    'email' => '',
                    'company' => 'Magento Commerce Inc.',
                    'street' => ['1220  Galts Ave'],
                    'regionId' => 66,
                    'city' => 'Red Deer',
                    'region' => 'Alberta',
                    'regionCode' => 'AB',
                    'postcode' => 'T4N 2A6',
                    'countryId' => 'CA',
                    'telephone' => '88776655'
                ]
            ],
        ];
    }

    /**
     * Assign requisite valid shipping address to quote, which is assigned in a separate API implementation
     *
     * @param CartInterface $quote
     * @return void
     */
    private function assignShippingAddressToQuote(CartInterface $quote)
    {
        $quote->getShippingAddress()->setQuote(
            $quote
        )->setRegion(
            'CA'
        )->setPostcode(
            '90210'
        )->setFirstname(
            'a_unique_firstname'
        )->setLastname(
            'lastname'
        )->setStreet(
            'street'
        )->setCity(
            'Beverly Hills'
        )->setEmail(
            'customer@example.com'
        )->setTelephone(
            '1111111111'
        )->setCountryId(
            'US'
        )->setAddressType(
            'shipping'
        )
        ->setShippingMethod('flatrate_flatrate')
        ->save();
    }

    /**
     * Get raw billing address payload to POST to purchase order creation endpoint
     *
     * @return array
     */
    private function getBillingAddressPayload()
    {
        return [
            'firstname' => 'John',
            'lastname' => 'Smith',
            'email' => '',
            'company' => 'Magento Commerce Inc.',
            'street' => ['Typical Street', 'Tiny House 18'],
            'city' => 'Big City',
            'region_id' => 12,
            'region' => 'California',
            'region_code' => 'CA',
            'postcode' => '0985432',
            'country_id' => 'US',
            'telephone' => '88776655',
            'fax' => '44332255',
        ];
    }

    /**
     * Get active quote created from fixture Magento/Checkout/_files/active_quote.php
     *
     * @param string $reservedOrderId
     * @return CartInterface
     */
    private function getActiveQuoteByReservedOrderId($reservedOrderId)
    {
        $searchCriteriaBuilder = clone $this->searchCriteriaBuilder;

        $filter = $this->filterBuilder->setField(CartInterface::KEY_RESERVED_ORDER_ID)
            ->setValue($reservedOrderId)
            ->create();

        $searchCriteriaBuilder->addFilters([$filter]);
        $searchCriteria = $searchCriteriaBuilder->create();

        $quotes = array_values($this->quoteRepository->getList($searchCriteria)->getItems());
        $quote = $quotes[count($quotes) - 1];

        return $quote;
    }

    /**
     * All existing company customer fixtures save password hash literally as 'password'; this updates with correct hash
     *
     * @param CustomerInterface $customer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function setCorrectPasswordHashOnCreatedCustomer($customer)
    {
        $customerModel = $this->customerRegistry->retrieveByEmail($customer->getEmail());
        $customerModel->setPassword('password')->save();
    }

    /**
     * Get access token to Web API for customer
     *
     * @param string $email
     * @param string $password
     * @return string
     * @throws AuthenticationException
     */
    private function getToken($email, $password)
    {
        return $this->customerTokenService->createCustomerAccessToken(
            $email,
            $password
        );
    }

    /**
     * Get web service info for the endpoint
     *
     * @param string $token
     * @return array
     */
    private function getServiceInfo($token = 'invalidtoken')
    {
        return [
            'soap' => [
                'service' => self::SERVICE_NAME,
                'operation' => self::SERVICE_NAME . 'savePaymentInformationAndPlacePurchaseOrder',
                'serviceVersion' => self::SERVICE_VERSION,
                'token' => $token
            ],
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $token
            ],
        ];
    }

    /**
     * Set B2B Features' company active status.
     *
     * magentoConfigFixture does not allow changing the value for website scope.
     *
     * @param bool $isActive
     * @param string $scope
     * @param int $scopeCode
     */
    private function setB2BFeaturesCompanyActiveStatus(bool $isActive, string $scope, $scopeCode = 0)
    {
        $this->coreConfigResourceModel->saveConfig(
            'btob/website_configuration/company_active',
            $isActive ? '1' : '0',
            $scope,
            $scopeCode
        );
    }

    /**
     * Set purchase order enabled status at system level.
     *
     * @param bool $isEnabled
     * @param string $scope
     * @param int $scopeCode
     */
    private function setPurchaseOrderEnabledStatus(
        bool $isEnabled,
        string $scope,
        $scopeCode = 0
    ) {
        $this->coreConfigResourceModel->saveConfig(
            'btob/website_configuration/purchaseorder_enabled',
            $isEnabled ? '1' : '0',
            $scope,
            $scopeCode
        );
    }

    /**
     * Set Purchase Order enabled status for customer's company
     *
     * @param CustomerInterface $customer
     * @param bool $isEnabled
     * @throws CouldNotSaveException
     */
    private function setPurchaseOrderEnabledStatusForCustomer(
        CustomerInterface $customer,
        bool $isEnabled
    ) {
        $companyId = $customer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId();
        $config = $this->purchaseOrderCompanyConfigRepository->get($companyId);
        $config->setIsPurchaseOrderEnabled($isEnabled);
        $this->purchaseOrderCompanyConfigRepository->save($config);
    }

    /**
     * Get Request Payload for the API call
     *
     * @param CartInterface $quote
     * @param string $paymentMethodCode
     * @return array
     */
    private function getRequestData(CartInterface $quote, string $paymentMethodCode = 'checkmo')
    {
        return [
            'cart_id' => $quote->getId(),
            'billingAddress' => $this->getBillingAddressPayload(),
            'paymentMethod' => [
                'method' => $paymentMethodCode,
                'po_number' => null,
                'additional_data' => null,
            ]
        ];
    }

    /**
     * Create a negotiable quote and apply a discount.
     *
     * @param CartInterface $quote
     */
    private function createNegotiableQuoteFromQuote(CartInterface $quote)
    {
        // Create a negotiable quote with a 10 percent discount
        /** @var NegotiableQuoteInterface $negotiableQuote */
        $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
        $negotiableQuote->setQuoteId($quote->getId())
            ->setQuoteName('Test Quote')
            ->setCreatorId($quote->getCustomer()->getId())
            ->setCreatorType(UserContextInterface::USER_TYPE_CUSTOMER)
            ->setIsRegularQuote(true)
            ->setNegotiatedPriceType(NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT)
            ->setNegotiatedPriceValue(10)
            ->setStatus(NegotiableQuoteInterface::STATUS_CREATED);
        $this->quoteRepository->save($quote);

        /** @var $negotiableQuoteHistoryManagement NegotiableQuoteHistoryManagementInterface */
        $negotiableQuoteHistoryManagement = $this->objectManager->get(NegotiableQuoteHistoryManagementInterface::class);
        $negotiableQuoteHistoryManagement->updateLog($quote->getId());

        // Recalculate the quote totals using the negotiated discount
        /** @var NegotiableQuoteManagementInterface $negotiableQuoteManagement */
        $negotiableQuoteManagement = $this->objectManager->get(NegotiableQuoteManagementInterface::class);
        $negotiableQuoteManagement->recalculateQuote($quote->getId(), true);

        // Simulate approval by the merchant
        $negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN);
        $this->quoteRepository->save($quote);

        $negotiableQuoteHistoryManagement->updateLog($quote->getId());

        // Reactivate the quote for checkout. Use ResourceModel to prevent Repository plugin from forcing to inactive.
        $quote->setIsActive(1);
        $quote->save();
    }

    /**
     * Force initialize sequence tables for purchase orders.
     *
     * @param array $storeIds
     */
    private function initSequence($storeIds = [])
    {
        foreach ($storeIds as $storeId) {
            $configSequence = $this->objectManager->get(\Magento\SalesSequence\Model\Config::class);
            $builder = $this->objectManager->get(\Magento\SalesSequence\Model\Builder::class);
            $builder->setPrefix($configSequence->get('prefix'))
                ->setSuffix($configSequence->get('suffix'))
                ->setStartValue($configSequence->get('startValue'))
                ->setStoreId($storeId)
                ->setStep($configSequence->get('step'))
                ->setWarningValue($configSequence->get('warningValue'))
                ->setMaxValue($configSequence->get('maxValue'))
                ->setEntityType('purchase_order')->create();
            $connection = $this->appResource->getConnection('default');
            // force create tables as original builder supposed to do
            if (!$connection->isTableExists($this->getSequenceName('purchase_order', $storeId))) {
                $connection->query(
                    $this->ddlSequence->getCreateSequenceDdl(
                        $this->getSequenceName('purchase_order', $storeId),
                        $configSequence->get('startValue')
                    )
                );
            }
        }
    }

    /**
     * Returns sequence table name.
     *
     * @param string $entityType
     * @param int $storeId
     * @return string
     */
    private function getSequenceName($entityType, $storeId)
    {
        return $this->appResource->getTableName(sprintf('sequence_%s_%s', $entityType, $storeId));
    }

    /**
     * Get service info for webapi get cart totals call
     *
     * @param string $cartId
     * @return array
     */
    private function getServiceInfoTotals($cartId)
    {
        return [
            'rest' => [
                'resourcePath' => str_replace(
                    ':cartId',
                    $cartId,
                    self::TOTALS_RESOURCE_PATH
                ),
                'httpMethod' => Request::HTTP_METHOD_GET
            ],
            'soap' => [
                'service' => self::TOTALS_SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::TOTALS_SERVICE_NAME . self::TOTALS_METHOD_NAME
            ],
        ];
    }

    /**
     * Get service info for webapi set cart billing address call
     *
     * @param string $cartId
     * @return array
     */
    private function getServiceInfoBillingAddress($cartId)
    {
        return [
            'rest' => [
                'resourcePath' => str_replace(
                    ':cartId',
                    $cartId,
                    self::BILLING_ADDRESS_RESOURCE_PATH
                ),
                'httpMethod' => Request::HTTP_METHOD_POST
            ],
            'soap' => [
                'service' => self::BILLING_ADDRESS_SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::BILLING_ADDRESS_SERVICE_NAME . self::BILLING_ADDRESS_METHOD_NAME,
            ],
        ];
    }
}
