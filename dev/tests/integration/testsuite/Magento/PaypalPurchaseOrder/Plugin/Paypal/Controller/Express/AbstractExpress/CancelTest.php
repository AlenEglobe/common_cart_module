<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PaypalPurchaseOrder\Plugin\Paypal\Controller\Express\AbstractExpress;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyPoConfigRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Test class for the PayPal Express Cancel controller plugin
 *
 * @see \Magento\PaypalPurchaseOrder\Plugin\Paypal\Controller\Express\AbstractExpress\Cancel
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CancelTest extends AbstractController
{
    /**
     * Url to dispatch.
     */
    private const URI = 'paypal/express/cancel';

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepository
     */
    private $purchaseOrderRepository;

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
        parent::setUp();
        $this->customerSession = $this->_objectManager->get(CustomerSession::class);
        $this->customerRepository = $this->_objectManager->get(CustomerRepository::class);
        $this->purchaseOrderRepository = $this->_objectManager->get(PurchaseOrderRepository::class);
        $this->companyRepository = $this->_objectManager->get(CompanyRepositoryInterface::class);
        $this->companyPoConfigRepository = $this->_objectManager->get(CompanyPoConfigRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->_objectManager->get(SearchCriteriaBuilder::class);

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
        $scopeConfig = $this->_objectManager->get(MutableScopeConfigInterface::class);
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
     * Get purchase order for the given customer.
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
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = array_shift($purchaseOrders);

        return $purchaseOrder;
    }

    /**
     * Test that on cancel, users are redirected to the purchase order details page instead of the active cart.
     *
     * This is dependent on a valid purchaseOrderId parameter being provided to the request.
     *
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/paypal_express/in_context 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider expectedRedirectPathDataProvider
     * @param $purchaseOrderId
     * @param $customerEmail
     * @param $creatorEmail
     * @param $expectedRedirectUri
     */
    public function testRedirectPathForPurchaseOrderCheckout(
        $purchaseOrderId,
        $customerEmail,
        $creatorEmail,
        $expectedRedirectUri
    ) {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Load the purchase order created by the specified user
        $purchaseOrder = $this->getPurchaseOrderForCustomer($creatorEmail);

        // Update this purchase order so that payment details can be provided at final checkout
        $purchaseOrder->setPaymentMethod('paypal_express');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Login as the user who will perform the checkout
        $customer = $this->customerRepository->get($customerEmail);
        $this->customerSession->loginById($customer->getId());

        // Build the cancel request uri
        if ($purchaseOrderId === 'fixture') {
            $purchaseOrderId = $purchaseOrder->getEntityId();
        }
        $cancelRequestUri = $purchaseOrderId ? (self::URI . '/purchaseOrderId/' . $purchaseOrderId) : self::URI;

        // Dispatch the cancel request
        $this->dispatch($cancelRequestUri);

        // If we're expecting a redirect to the purchase order details page, append the id from the fixture
        if (str_starts_with($expectedRedirectUri, 'purchaseorder')) {
            $expectedRedirectUri .= "/$purchaseOrderId";
        }

        // Assert that the user is redirected to the expected uri
        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->stringContains($expectedRedirectUri));

        $this->customerSession->logout();
    }

    /**
     * @return array
     */
    public function expectedRedirectPathDataProvider()
    {
        return [
            'po_checkout' => [
                'purchase_order_id' => 'fixture',
                'customer_email' => 'alex.smith@example.com',
                'creator_email' => 'alex.smith@example.com',
                'expected_redirect_uri' => 'purchaseorder/purchaseorder/view/request_id'
            ],
            'po_checkout_absent_po_id' => [
                'purchase_order_id' => null,
                'customer_email' => 'alex.smith@example.com',
                'creator_email' => 'alex.smith@example.com',
                'expected_redirect_uri' => 'checkout/cart'
            ],
            'po_checkout_invalid_po_id' => [
                'purchase_order_id' => 999999,
                'customer_email' => 'alex.smith@example.com',
                'creator_email' => 'alex.smith@example.com',
                'expected_redirect_uri' => 'checkout/cart'
            ],
            'po_checkout_non_creator' => [
                'purchase_order_id' => 'fixture',
                'customer_email' => 'john.doe@example.com',
                'creator_email' => 'alex.smith@example.com',
                'expected_redirect_uri' => 'checkout/cart'
            ]
        ];
    }
}
