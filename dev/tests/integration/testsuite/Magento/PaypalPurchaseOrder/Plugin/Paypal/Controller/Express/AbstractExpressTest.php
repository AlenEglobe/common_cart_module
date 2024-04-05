<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaypalPurchaseOrder\Plugin\Paypal\Controller\Express;

use Magento\Checkout\Model\Session;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Paypal\Model\Api\Nvp;
use Magento\Paypal\Model\Api\Type\Factory as ApiFactory;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyConfigRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Tests of \Magento\PaypalPurchaseOrder\Plugin\Paypal\Controller\Express\AbstractExpress plugin that is applied to
 * Magento\Paypal\Controller\Express\AbstractExpress.
 * Covers \Magento\PaypalPurchaseOrder\Plugin\Paypal\Model\Api\NvpPlugin as well.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AbstractExpressTest extends \Magento\TestFramework\TestCase\AbstractController
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Nvp
     */
    private $nvp;

    /**
     * @var ApiFactory
     */
    private $apiTypeFactory;

    /**
     * @var \Magento\Framework\HTTP\Adapter\Curl|\PHPUnit\Framework\MockObject\MockObject
     */
    private $httpClient;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var CompanyConfigRepository
     */
    private $companyConfigRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->companyRepository = $this->_objectManager->get(CompanyRepositoryInterface::class);
        $this->companyConfigRepository = $this->_objectManager->get(CompanyConfigRepository::class);
        $this->customerRepository = $this->_objectManager->get(CustomerRepositoryInterface::class);
        $this->customerSession = $this->_objectManager->get(\Magento\Customer\Model\Session::class);
        $this->request = $this->_objectManager->get(RequestInterface::class);
        $this->apiTypeFactory = $this->getMockBuilder(ApiFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        /** @var CurlFactory|\PHPUnit\Framework\MockObject\MockObject $httpFactory */
        $httpFactory = $this->getMockBuilder(CurlFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->httpClient = $this->getMockBuilder(Curl::class)
            ->disableOriginalConstructor()
            ->getMock();
        $httpFactory->method('create')
            ->willReturn($this->httpClient);

        $this->nvp = $this->_objectManager->create(Nvp::class, [
            'curlFactory' => $httpFactory
        ]);

        $this->_objectManager->addSharedInstance($this->apiTypeFactory, ApiFactory::class);
        $this->apiTypeFactory->expects($this->any())
            ->method('create')
            ->with(Nvp::class)
            ->willReturn($this->nvp);
        $this->searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);
        $this->checkoutSession = $this->_objectManager->get(Session::class);
        $this->purchaseOrderRepository = $this->_objectManager->get(PurchaseOrderRepositoryInterface::class);

        // Enable company functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);
    }

    /**
     * Verify that when paypal checkout start is parametrized with purchase order id it is stored in checkout session
     * and properly fetched on return.
     *
     * @covers \Magento\PaypalPurchaseOrder\Plugin\Paypal\Model\Api\NvpPlugin
     * @magentoDataFixture Magento/PaypalPurchaseOrder/_files/company_with_purchase_orders_customer_addresses.php
     * @magentoAppArea frontend
     */
    public function testStartAndReturnActions()
    {
        $testToken = 'EC-9X008318LT671010S';
        $expectedResponse = 'HTTP/1.1 200 OK' . "\n"
            . 'Cache-Control: max-age=0, no-cache, no-store, must-revalidate' . "\n"
            . 'Content-Length: 137' . "\n"
            . 'Content-Type: text/plain; charset=utf-8' . "\n"
            . 'Date: Mon, 14 Dec 2020 17:54:30 GMT' . "\n"
            . 'Paypal-Debug-Id: 7c270c71ac80a'  . "\n"
            . 'X-Paypal-Api-Rc:' . "\n"
            . 'X-Paypal-Operation-Name: SetExpressCheckout' . "\n"
            . 'X-Slr-Retry-Api: SetExpressCheckout' . "\n\n"
            . 'TOKEN=' . urlencode($testToken) . '&TIMESTAMP=2020%2d12%2d14T17%3a54%3a31Z&'
            . 'CORRELATIONID=7c270c71ac80a&ACK=Success&VERSION=72%2e0&BUILD=55100925';
        $this->httpClient->expects($this->any())->method('read')->willReturn($expectedResponse);
        $this->setCompanyPurchaseOrderConfig('Magento', true);
        // Log in as the current user
        $currentUser = $this->customerRepository->get('john.doe@example.com');
        $this->customerSession->loginById($currentUser->getId());
        $purchaseOrder = $this->getCustomerPurchaseOrder('john.doe@example.com');
        $this->getRequest()->setParams(['purchaseOrderId' => $purchaseOrder->getEntityId()]);
        $this->dispatch('paypal/express/start');
        $sessionData = $this->checkoutSession->getData();
        $this->assertEquals(
            (int)$purchaseOrder->getEntityId(),
            $sessionData['purchaseOrder_' . $testToken],
            'Purchase Order ID is not properly set to checkout session by token'
        );
        $this->checkoutSession->setData('purchaseOrder_' . $testToken, $purchaseOrder->getEntityId());
        $this->getRequest()->setParams(['purchaseOrderId' => null, 'token' => $testToken]);
        $this->dispatch('paypal/express/return');
        $this->assertEquals(
            (int)$purchaseOrder->getEntityId(),
            (int)$this->getRequest()->getParam('purchaseOrderId'),
            'Purchase order ID is not properly fetched from session by token'
        );
    }

    /**
     * Verify that proper purchase order ID is retrieved from session and placed to request on secondary paypal actions.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoAppArea frontend
     * @dataProvider secondaryActionsDataProvider()
     * @param string $action
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSecondaryActions($action)
    {
        $this->setCompanyPurchaseOrderConfig('Magento', true);
        $testToken = 'EC-9X008318LT671010S';
        // Log in as the current user
        $currentUser = $this->customerRepository->get('john.doe@example.com');
        $this->customerSession->loginById($currentUser->getId());
        $purchaseOrder = $this->getCustomerPurchaseOrder('john.doe@example.com');
        $this->checkoutSession->setData('purchaseOrder_' . $testToken, $purchaseOrder->getEntityId());
        $this->getRequest()->setParams(['token' => $testToken]);
        $this->dispatch($action);
        $this->assertEquals(
            (int)$purchaseOrder->getEntityId(),
            (int)$this->getRequest()->getParam('purchaseOrderId'),
            'Failed to fetch Purchase Order ID from session by token and pass it as request param'
        );
    }

    /**
     * Data provider for secondary actions test.
     *
     * @return \string[][]
     */
    public function secondaryActionsDataProvider()
    {
        return [
            'PlaceOrder action' => ['paypal/express/placeOrder'],
            'Review action' => ['paypal/express/review'],
            'Edit action' => ['paypal/express/edit'],
            'Cancel action' => ['paypal/express/cancel'],
            'SaveShippingMethod action' => ['paypal/express/saveShippingMethod'],
            'ShippingOptionsCallback action' => ['paypal/express/shippingOptionsCallback'],
            'UpdateShippingMethods action' => ['paypal/express/updateShippingMethods'],
        ];
    }

    /**
     * Get first purchase order created by customer with given email.
     *
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCustomerPurchaseOrder(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }

    /**
     * Enable/Disable purchase order functionality on a per company basis.
     *
     * @param string $companyName
     * @param bool $isEnabled
     * @throws LocalizedException
     */
    private function setCompanyPurchaseOrderConfig(string $companyName, bool $isEnabled)
    {
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->companyRepository->getList($searchCriteria)->getItems();
        /** @var CompanyInterface $company */
        $company = reset($results);
        $companyConfig = $this->companyConfigRepository->get($company->getId());
        $this->companyConfigRepository->save($companyConfig->setIsPurchaseOrderEnabled($isEnabled));
    }

    /**
     * Enable/Disable configuration for the website scope (not available via magentoConfigFixture).
     *
     * @param string $path
     * @param bool $isEnabled
     */
    private function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue($path, $isEnabled ? '1' : '0', ScopeInterface::SCOPE_WEBSITE);
    }
}
