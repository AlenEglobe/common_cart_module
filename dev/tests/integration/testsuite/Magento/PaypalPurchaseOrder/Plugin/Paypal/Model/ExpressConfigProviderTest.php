<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\PaypalPurchaseOrder\Plugin\Paypal\Model;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Paypal\Model\ExpressConfigProvider;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyPoConfigRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Test class for PayPal Express config provider plugin
 *
 * @see \Magento\PaypalPurchaseOrder\Plugin\Paypal\Model\ExpressConfigProvider
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ExpressConfigProviderTest extends TestCase
{
    /**
     * @var ExpressConfigProvider
     */
    private $expressConfigProvider;

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
     * @var RequestInterface
     */
    private $request;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = ObjectManager::getInstance();
        $this->expressConfigProvider = $objectManager->get(ExpressConfigProvider::class);
        $this->customerSession = $objectManager->get(CustomerSession::class);
        $this->customerRepository = $objectManager->get(CustomerRepository::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepository::class);
        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->companyPoConfigRepository = $objectManager->get(CompanyPoConfigRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->request = $objectManager->get(RequestInterface::class);

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
     * Test that when the PayPal Express Checkout payment method is enabled (in-context disabled),
     * the Express config provider updates its urls relative to purchase orders.
     *
     * The purchaseOrderId should be appended to redirect urls.
     *
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/paypal_express/in_context 0
     * @magentoConfigFixture current_store payment/paypal_express_bml/active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testAfterGetConfigPaypalExpressOutOfContext()
    {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as the purchase order creator
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        // Update this purchase order so that payment details can be provided at final checkout
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setPaymentMethod('paypal_express');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Set the purchaseOrderId request parameter
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->request->setParams(['purchaseOrderId' => $purchaseOrderId]);

        // Get the actual PayPal Express configs
        $expressConfig = $this->expressConfigProvider->getConfig();

        // Assert that the redirect urls are appended with the purchaseOrderId parameter.
        $purchaseOrderParam = '/purchaseOrderId/' . $purchaseOrderId;
        $this->assertRedirectUrlsUpdated($expressConfig, 'paypal_express', $purchaseOrderParam);
    }

    /**
     * Test that when the PayPal Express Checkout payment method is enabled (in-context enabled),
     * the Express config provider updates its urls relative to purchase orders.
     *
     * The purchaseOrderId should be appended to both in-context AND redirect urls.
     *
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/paypal_express/in_context 1
     * @magentoConfigFixture current_store payment/paypal_express_bml/active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testAfterGetConfigPaypalExpressInContext()
    {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as the purchase order creator
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        // Update this purchase order so that payment details can be provided at final checkout
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setPaymentMethod('paypal_express');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Set the purchaseOrderId request parameter
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->request->setParams(['purchaseOrderId' => $purchaseOrderId]);

        // Get the actual PayPal Express configs
        $expressConfig = $this->expressConfigProvider->getConfig();

        // Assert that both the in-context and redirect urls are appended with the purchaseOrderId parameter.
        $purchaseOrderParam = '/purchaseOrderId/' . $purchaseOrderId;
        $this->assertInContextUrlsUpdated($expressConfig, $purchaseOrderParam);
        $this->assertRedirectUrlsUpdated($expressConfig, 'paypal_express', $purchaseOrderParam);
    }

    /**
     * Test that when any PayPal payment method payment method which includes Payflow Express is enabled,
     * the Express config provider updates its urls relative to purchase orders.
     *
     * This includes PayPal Payments Pro/Advanced and PayPal Payflow Pro/Link
     * The purchaseOrderId should be appended to redirect urls.
     *
     * @magentoConfigFixture current_store payment/payflow_express/active 1
     * @magentoConfigFixture current_store payment/payflow_express_bml/active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testAfterGetConfigPayflowExpress()
    {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as the purchase order creator
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        // Update this purchase order so that payment details can be provided at final checkout
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setPaymentMethod('payflow_express');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Set the purchaseOrderId request parameter
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->request->setParams(['purchaseOrderId' => $purchaseOrderId]);

        // Get the actual PayPal Express configs
        $expressConfig = $this->expressConfigProvider->getConfig();

        // Assert that the redirect urls are appended with the purchaseOrderId parameter.
        $purchaseOrderParam = '/purchaseOrderId/' . $purchaseOrderId;
        $this->assertRedirectUrlsUpdated($expressConfig, 'payflow_express', $purchaseOrderParam);
    }

    /**
     * Test that when the PayPal Payments Standard payment method is enabled, the Express config provider updates its
     * urls relative to purchase orders.
     *
     * The purchaseOrderId should be appended to redirect urls.
     *
     * @magentoConfigFixture current_store payment/wps_express/active 1
     * @magentoConfigFixture current_store payment/wps_express_bml/active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testAfterGetConfigWpsExpress()
    {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as the purchase order creator
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        // Update this purchase order so that payment details can be provided at final checkout
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setPaymentMethod('paypal_express');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Set the purchaseOrderId request parameter
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->request->setParams(['purchaseOrderId' => $purchaseOrderId]);

        // Get the actual PayPal Express configs
        $expressConfig = $this->expressConfigProvider->getConfig();

        // Assert that the redirect urls are appended with the purchaseOrderId parameter.
        $purchaseOrderParam = '/purchaseOrderId/' . $purchaseOrderId;
        $this->assertRedirectUrlsUpdated($expressConfig, 'paypal_express', $purchaseOrderParam);
    }

    /**
     * Test that when an invalid purchaseOrderId is provided, the Express config provider does NOT update its
     * urls relative to purchase orders.
     *
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/paypal_express/in_context 1
     * @magentoConfigFixture current_store payment/paypal_express_bml/active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testAfterGetConfigInvalidPurchaseOrderId()
    {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as the purchase order creator
        $customer = $this->customerRepository->get('customer@example.com');
        $this->customerSession->loginById($customer->getId());

        // Set an invalid purchaseOrderId request parameter
        $invalidPurchaseOrderId = '999999999';
        $this->request->setParams(['purchaseOrderId' => $invalidPurchaseOrderId]);

        // Get the actual PayPal Express configs
        $expressConfig = $this->expressConfigProvider->getConfig();

        // Assert that the in-context and redirect urls are NOT appended with the purchaseOrderId parameter.
        $purchaseOrderParam = '/purchaseOrderId/' . $invalidPurchaseOrderId;
        $this->assertInContextUrlsNotUpdated($expressConfig, $purchaseOrderParam);
        $this->assertRedirectUrlsNotUpdated($expressConfig, 'paypal_express', $purchaseOrderParam);
    }

    /**
     * Test that when the a customer who is not the purchase order creator attempts to complete checkout,
     * the Express config provider does NOT update its urls relative to purchase orders.
     *
     * @magentoConfigFixture current_store payment/paypal_express/active 1
     * @magentoConfigFixture current_store payment/paypal_express/in_context 1
     * @magentoConfigFixture current_store payment/paypal_express_bml/active 1
     * @magentoConfigFixture default/btob/default_b2b_payment_methods/applicable_payment_methods 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testAfterGetConfigNonPurchaseOrderCreator()
    {
        // Configure the company to use purchase orders
        $company = $this->getCompanyByName('Magento');
        $this->setCompanyPurchaseOrderConfig($company, true);

        // Login as the company admin
        $customer = $this->customerRepository->get('john.doe@example.com');
        $this->customerSession->loginById($customer->getId());

        // Load the purchase order for a different company user
        $purchaseOrder = $this->getPurchaseOrderForCustomer('alex.smith@example.com');

        // Update this purchase order so that payment details can be provided at final checkout
        $purchaseOrder->setPaymentMethod('paypal_express');
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Set the purchaseOrderId request parameter
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->request->setParams(['purchaseOrderId' => $purchaseOrderId]);

        // Get the actual PayPal Express configs
        $expressConfig = $this->expressConfigProvider->getConfig();

        // Assert that the in-context and redirect urls are NOT appended with the purchaseOrderId parameter.
        $purchaseOrderParam = '/purchaseOrderId/' . $purchaseOrderId;
        $this->assertInContextUrlsNotUpdated($expressConfig, $purchaseOrderParam);
        $this->assertRedirectUrlsNotUpdated($expressConfig, 'paypal_express', $purchaseOrderParam);
    }

    /**
     * Assert that the PayPal in-context urls are appended with the provided purchase order parameter.
     *
     * @param array $expressConfig
     * @param string $purchaseOrderParam
     */
    private function assertInContextUrlsUpdated($expressConfig, $purchaseOrderParam)
    {
        $this->assertNotEmpty($expressConfig['payment']['paypalExpress']['inContextConfig']['clientConfig']);
        $inContextConfig = $expressConfig['payment']['paypalExpress']['inContextConfig']['clientConfig'];

        $this->assertNotEmpty($inContextConfig['getTokenUrl']);
        $this->assertStringEndsWith($purchaseOrderParam, $inContextConfig['getTokenUrl']);
        $this->assertNotEmpty($inContextConfig['onAuthorizeUrl']);
        $this->assertStringEndsWith($purchaseOrderParam, $inContextConfig['onAuthorizeUrl']);
        $this->assertNotEmpty($inContextConfig['onCancelUrl']);
        $this->assertStringEndsWith($purchaseOrderParam, $inContextConfig['onCancelUrl']);
    }

    /**
     * Assert that the PayPal in-context urls are not appended with the provided purchase order parameter.
     *
     * @param array $expressConfig
     * @param string $purchaseOrderParam
     */
    private function assertInContextUrlsNotUpdated($expressConfig, $purchaseOrderParam)
    {
        $this->assertNotEmpty($expressConfig['payment']['paypalExpress']['inContextConfig']['clientConfig']);
        $inContextConfig = $expressConfig['payment']['paypalExpress']['inContextConfig']['clientConfig'];

        $this->assertNotEmpty($inContextConfig['getTokenUrl']);
        $this->assertStringNotContainsString($purchaseOrderParam, $inContextConfig['getTokenUrl']);
        $this->assertNotEmpty($inContextConfig['onAuthorizeUrl']);
        $this->assertStringNotContainsString($purchaseOrderParam, $inContextConfig['onAuthorizeUrl']);
        $this->assertNotEmpty($inContextConfig['onCancelUrl']);
        $this->assertStringNotContainsString($purchaseOrderParam, $inContextConfig['onCancelUrl']);
    }

    /**
     * Assert that the PayPal redirect urls are appended with the provided purchase order parameter.
     *
     * @param array $expressConfig
     * @param string $paymentMethod
     * @param string $purchaseOrderParam
     */
    private function assertRedirectUrlsUpdated($expressConfig, $paymentMethod, $purchaseOrderParam)
    {
        $this->assertNotEmpty($expressConfig['payment']['paypalExpress']['redirectUrl']);
        $redirectUrlConfig = $expressConfig['payment']['paypalExpress']['redirectUrl'];

        $this->assertNotEmpty($redirectUrlConfig[$paymentMethod]);
        $this->assertStringEndsWith($purchaseOrderParam, $redirectUrlConfig[$paymentMethod]);
        $this->assertNotEmpty($redirectUrlConfig[$paymentMethod . '_bml']);
        $this->assertStringEndsWith($purchaseOrderParam, $redirectUrlConfig[$paymentMethod . '_bml']);
    }

    /**
     * Assert that the PayPal in-context urls are not appended with the provided purchase order parameter.
     *
     * @param array $expressConfig
     * @param string $paymentMethod
     * @param string $purchaseOrderParam
     */
    private function assertRedirectUrlsNotUpdated($expressConfig, $paymentMethod, $purchaseOrderParam)
    {
        $this->assertNotEmpty($expressConfig['payment']['paypalExpress']['redirectUrl']);
        $redirectUrlConfig = $expressConfig['payment']['paypalExpress']['redirectUrl'];

        $this->assertNotEmpty($redirectUrlConfig[$paymentMethod]);
        $this->assertStringNotContainsString($purchaseOrderParam, $redirectUrlConfig[$paymentMethod]);
        $this->assertNotEmpty($redirectUrlConfig[$paymentMethod . '_bml']);
        $this->assertStringNotContainsString($purchaseOrderParam, $redirectUrlConfig[$paymentMethod . '_bml']);
    }
}
