<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Service\V1;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Webapi\Rest\Request;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\PurchaseOrderManagementInterface;

/**
 * Class for testing Purchase Order Payment Details Page Api calls
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class PurchaseOrderPaymentDetailsTest extends WebapiAbstract
{
    /**
     * API Services configuration
     */
    private const SERVICE_VERSION = 'V1';
    private const TOTALS_SERVICE_NAME = 'quoteCartTotalRepositoryV1';
    private const TOTALS_RESOURCE_PATH = '/V1/purchase-order-carts/:cartId/totals';
    private const TOTALS_METHOD_NAME = 'get';
    private const BILLING_ADDRESS_SERVICE_NAME = 'quoteBillingAddressManagementV1';
    private const BILLING_ADDRESS_RESOURCE_PATH = '/V1/purchase-order-carts/:cartId/billing-address';
    private const BILLING_ADDRESS_METHOD_NAME = 'assign';

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRegistry
     */
    private $customerRegistry;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var PurchaseOrderManagementInterface
     */
    private $purchaseOrderManagment;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);
        $this->customerRegistry = $objectManager->get(CustomerRegistry::class);
        $this->cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $this->purchaseOrderManagment = $objectManager->get(PurchaseOrderManagementInterface::class);
    }

    /**
     * Test payment details page taxes calculation based on Billing Address.
     *
     * @param string $customerEmail purcahse order customer creator email
     * @param int $expectedTaxAmount expected tax amount
     * @param array $billingAddressPayload raw billing address payload to POST purchase order creation endpoint
     * @throws NoSuchEntityException
     * @dataProvider paymentDetailsTaxBillingAddressBasedDataProvider
     * @magentoConfigFixture default_store tax/calculation/based_on billing
     * @magentoConfigFixture default_store btob/website_configuration/company_active 1
     * @magentoConfigFixture default_store btob/website_configuration/purchaseorder_enabled 1
     * @magentoApiDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_address_tax.php
     */
    public function testPaymentDetailsPageTaxBillingAddressBased(
        string $customerEmail,
        int $expectedTaxAmount,
        array $billingAddressPayload
    ) {
        $customer = $this->customerRegistry->retrieveByEmail($customerEmail);
        $this->session->setCustomerAsLoggedIn($customer);
        $purchaseOrderId = $this->getPurchaseOrderForCustomer($customerEmail)->getEntityId();
        $purchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);

        $initialTotalsResult = $this->_webApiCall(
            $this->getServiceInfoTotals($purchaseOrder),
            ['cartId' => $purchaseOrder->getQuoteId()]
        );

        $this->assertEquals($expectedTaxAmount, $initialTotalsResult['tax_amount']);

        $this->_webApiCall(
            $this->getServiceInfoBillingAddress($purchaseOrder),
            [
                'cart_id' => $purchaseOrder->getQuoteId(),
                'address' => $billingAddressPayload
            ]
        );

        $updatedTotalResults = $this->_webApiCall(
            $this->getServiceInfoTotals($purchaseOrder),
            ['cartId' => $purchaseOrder->getQuoteId()]
        );

        $this->assertEquals($expectedTaxAmount, $updatedTotalResults['tax_amount']);

        $this->purchaseOrderManagment->approvePurchaseOrder($purchaseOrder, $customer->getId());
        $order = $this->purchaseOrderManagment->createSalesOrder($purchaseOrder, $customer->getId());

        $this->assertEquals($purchaseOrder->getGrandTotal(), $order->getGrandTotal());
        $this->assertEquals($expectedTaxAmount, $order->getTaxAmount());
    }

    /**
     * Data provider for taxes test based on billing address
     *
     * @return array[]
     */
    public function paymentDetailsTaxBillingAddressBasedDataProvider()
    {
        return [
            'default' => [
                'customerEmail' => 'customer@example.com',
                'expectedTaxAmount' => 4,
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
     * Get service info for webapi get cart totals call
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @return array
     */
    private function getServiceInfoTotals(PurchaseOrderInterface $purchaseOrder)
    {
        return [
            'rest' => [
                'resourcePath' => str_replace(
                    ':cartId',
                    $purchaseOrder->getQuoteId(),
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
     * @param PurchaseOrderInterface $purchaseOrder
     * @return array
     */
    private function getServiceInfoBillingAddress(PurchaseOrderInterface $purchaseOrder)
    {
        return [
            'rest' => [
                'resourcePath' => str_replace(
                    ':cartId',
                    $purchaseOrder->getQuoteId(),
                    self::BILLING_ADDRESS_RESOURCE_PATH
                ),
                'httpMethod' => Request::HTTP_METHOD_POST
            ],
            'soap' => [
                'service' => self::BILLING_ADDRESS_SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::BILLING_ADDRESS_SERVICE_NAME . self::BILLING_ADDRESS_METHOD_NAME
            ],
        ];
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->customerRegistry->retrieveByEmail($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }
}
