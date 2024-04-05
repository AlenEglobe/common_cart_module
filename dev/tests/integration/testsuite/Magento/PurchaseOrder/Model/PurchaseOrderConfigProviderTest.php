<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\RequestInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderConfigProvider;
use Magento\Framework\UrlInterface;

/**
 * Test Class for purchase order checkout config provider
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class PurchaseOrderConfigProviderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var PurchaseOrderConfigProvider
     */
    private $purchaseOrderConfigProvider;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->request = $this->objectManager->get(RequestInterface::class);
        $this->purchaseOrderConfigProvider = $this->objectManager->get(PurchaseOrderConfigProvider::class);
        $this->urlBuilder = $this->objectManager->get(UrlInterface::class);
    }

    /**
     * Test purchase order config provider data
     *
     * @dataProvider configDataProvider
     * @magentoConfigFixture current_store payment/checkmo/active 1
     * @magentoConfigFixture current_store payment/fake/active 0
     * @magentoConfigFixture current_store payment/fake_vault/active 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testGetConfig(
        $customerEmail,
        $expectedResult
    ) {
        $purchaseOrder = $this->getPurchaseOrderForCustomer($customerEmail);
        $expectedResult['purchaseOrderQuoteId'] = $purchaseOrder->getQuoteId();
        $expectedResult['purchaseOrderPaymentUrl'] = $this->urlBuilder->getUrl(
            'checkout/index/index',
            [
                'purchaseOrderId' => $purchaseOrder->getEntityId()
            ]
        );

        $this->request->setParams([
            'purchaseOrderId' => $purchaseOrder->getEntityId()
        ]);
        $resultConfig = $this->purchaseOrderConfigProvider->getConfig();
        $this->assertEquals($expectedResult, $resultConfig);
    }

    /**
     * Data provider for various config scenarios
     *
     * @return array
     */
    public function configDataProvider()
    {
        return [
            'po_creator' => [
                'customer_email' => 'john.doe@example.com',
                'expected_result' => [
                    'isPurchaseOrder' => true,
                    'paymentMethods' => [
                        [
                            'code' => 'checkmo',
                            'title' => 'Check / Money order'
                        ]
                    ],
                    'purchaseOrderQuoteId' => '',
                    'purchaseOrderPaymentUrl' => '',
                    'purchaseOrderShippingAddress' => [
                        'city' => "CityM",
                        'company' => 'CompanyName',
                        'country_id' => 'US',
                        'firstname' => 'John',
                        'lastname' => 'Smith',
                        'postcode' => '75477',
                        'region' => 'Alabama',
                        'region_id' => '1',
                        'street' => (object) ['Green str, 67'],
                        'telephone' => '3468676',
                        'customer_address_id' => '1'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get purchase order for the given customer email.
     *
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }
}
