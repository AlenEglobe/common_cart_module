<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyPayment\Controller\Adminhtml\Order;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\CompanyPayment\Model\CompanyPaymentMethodFactory;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as PurchaseOrderConfigRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\ResponseInterface;

/**
 * Test Class for B2B payment method settings by admin create order flow
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractCreateTest extends AbstractBackendController
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var CompanyPaymentMethodFactory
     */
    private $companyPaymentMethodFactory;

    /**
     * @var PurchaseOrderConfigRepositoryInterface
     */
    private $purchaseOrderConfigRepository;

    /**
     * @inheritDoc
     *
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configFactory = $this->_objectManager->get(ConfigFactory::class);
        $this->companyPaymentMethodFactory = $this->_objectManager->get(CompanyPaymentMethodFactory::class);
        $this->purchaseOrderConfigRepository = $this->_objectManager->get(
            PurchaseOrderConfigRepositoryInterface::class
        );
    }

    public function testCompanyCreateOrderPaymentMethods()
    {
        $data = $this->companyPaymentMethodsTestData();
        $this->preformTestCompanyCreateOrderPaymentMethods(
            $data['salesPaymentMethodsConfig'],
            $data['companyPaymentMethodConfig'],
            $data['expectedResultCompanyCustomer'],
            $data['expectedResultNonCompanyCustomer']
        );
    }

    public function testCompanyCreateOrderPaymentMethodsWithPurchaseOrderEnabled()
    {
        $data = $this->companyPaymentMethodsTestData();
        $this->performTestCompanyCreateOrderPaymentMethodsWithPurchaseOrderEnabled(
            $data['salesPaymentMethodsConfig'],
            $data['companyPaymentMethodConfig'],
            $data['expectedResultCompanyCustomer'],
            $data['expectedResultNonCompanyCustomer']
        );
    }

    /**
     * Test admin create order payments for company/non company customers
     *
     * @param array $salesPaymentMethodsConfig
     * @param array $companyPaymentMethodConfig
     * @param array $expectedResultCompanyCustomer
     * @param array $expectedResultNonCompanyCustomer
     */
    private function preformTestCompanyCreateOrderPaymentMethods(
        array $salesPaymentMethodsConfig,
        array $companyPaymentMethodConfig,
        array $expectedResultCompanyCustomer,
        array $expectedResultNonCompanyCustomer
    ) {
        $this->setConfigValues($salesPaymentMethodsConfig);

        $nonCompanyCustomer = $this->_objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com');
        $quote = $this->_objectManager->get(CartRepositoryInterface::class)->getForCustomer(1);

        $quote->setCustomerId($nonCompanyCustomer->getId());
        $quote->setCustomer($nonCompanyCustomer);
        $quote->collectTotals()->save();

        $session = $this->_objectManager->get(SessionQuote::class);

        $session->setQuoteId($quote->getId());

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->getRequest()->setPostValue(
            [
                'customer_id' => 1,
                'block' => 'billing_method',
                'store_id' => 1,
                'json' => true
            ]
        );

        $this->dispatch('backend/sales/order_create/loadBlock');
        $html = $this->getResponse()->getBody();

        $this->assertResults($expectedResultNonCompanyCustomer, $html);

        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        Bootstrap::getInstance()->loadArea('adminhtml');
        $this->resetRequest();
        $this->resetResponse();

        $companyCustomer = $this->_objectManager->get(CustomerRepositoryInterface::class)->get('alex.smith@example.com');
        $company = $this->_objectManager->get(CompanyRepositoryInterface::class)->get(
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

        $session = $this->_objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->getRequest()->setPostValue(
            [
                'customer_id' => $companyCustomer->getId(),
                'block' => 'billing_method',
                'store_id' => 1,
                'json' => true
            ]
        );

        $this->dispatch('backend/sales/order_create/loadBlock');
        $html = $this->getResponse()->getBody();

        $this->assertResults($expectedResultCompanyCustomer, $html);
    }

    /**
     * Test admin create order payments for company/non company customers with purchase order enabled
     *
     * @param array $salesPaymentMethodsConfig
     * @param array $companyPaymentMethodConfig
     * @param array $expectedResultCompanyCustomer
     * @param array $expectedResultNonCompanyCustomer
     */
    private function performTestCompanyCreateOrderPaymentMethodsWithPurchaseOrderEnabled(
        array $salesPaymentMethodsConfig,
        array $companyPaymentMethodConfig,
        array $expectedResultCompanyCustomer,
        array $expectedResultNonCompanyCustomer
    ) {
        $salesPaymentMethodsConfig[ScopeConfigInterface::SCOPE_TYPE_DEFAULT]
        ['default']['btob/order_approval/purchaseorder_active'] = 1;
        $this->setConfigValues($salesPaymentMethodsConfig);

        $nonCompanyCustomer = $this->_objectManager->get(CustomerRepositoryInterface::class)->get('customer@example.com');
        $quote = $this->_objectManager->get(CartRepositoryInterface::class)->getForCustomer(1);

        $quote->setCustomerId($nonCompanyCustomer->getId());
        $quote->setCustomer($nonCompanyCustomer);
        $quote->collectTotals()->save();

        $session = $this->_objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->getRequest()->setPostValue(
            [
                'customer_id' => 1,
                'block' => 'billing_method',
                'store_id' => 1,
                'json' => true
            ]
        );

        $this->dispatch('backend/sales/order_create/loadBlock');
        $html = $this->getResponse()->getBody();

        $this->assertResults($expectedResultNonCompanyCustomer, $html);

        Bootstrap::getInstance()->getBootstrap()->getApplication()->reinitialize();
        Bootstrap::getInstance()->loadArea('adminhtml');
        $this->resetRequest();
        $this->resetResponse();

        $companyCustomer = $this->_objectManager->get(CustomerRepositoryInterface::class)->get('alex.smith@example.com');
        $company = $this->_objectManager->get(CompanyRepositoryInterface::class)->get(
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

        $session = $this->_objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);

        $this->getRequest()->setPostValue(
            [
                'customer_id' => $companyCustomer->getId(),
                'block' => 'billing_method',
                'store_id' => 1,
                'json' => true
            ]
        );

        $this->dispatch('backend/sales/order_create/loadBlock');
        $html = $this->getResponse()->getBody();

        $this->assertResults($expectedResultCompanyCustomer, $html);
    }

    /**
     * Assert results
     *
     * @param array $expectedResults
     * @param string $html
     */
    private function assertResults(array $expectedResults, string $html)
    {
        foreach ($expectedResults as $type => $expectedResultsHtml) {
            foreach ($expectedResultsHtml as $expectedHtml) {
                ($type == 'enabled') ?
                    $this->assertStringContainsString($expectedHtml, $html) :
                    $this->assertStringNotContainsString($expectedHtml, $html);
            }
        }
    }

    /**
     * Payment methods data
     *
     * @return array[]
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    abstract protected function companyPaymentMethodsTestData();

    /**
     * Update scope config settings
     * @param array $configData
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
    }

    /**
     * Reset response singleton to allow multiple dispatches in the same test
     */
    private function resetResponse()
    {
        Bootstrap::getObjectManager()->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && 0 !== strpos($property->getDeclaringClass()->getName(), 'PHPUnit')) {
                $property->setAccessible(true);
                $property->setValue($this, null);
            }
        }
    }
}
