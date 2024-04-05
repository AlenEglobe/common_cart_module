<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Abstract class for PurchaseOrder tests
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
abstract class PurchaseOrderAbstract extends AbstractController
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $this->objectManager = Bootstrap::getObjectManager();
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $this->objectManager->get(Session::class);

        $scopeConfig = $this->objectManager->get(MutableScopeConfigInterface::class);
        // Enable company functionality at the system level
        $scopeConfig->setValue(
            'btob/website_configuration/company_active',
            true ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $purchaseOrders =  $purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }

    /**
     * Get purchase order for test
     *
     * @param string $companyAdminEmail
     * @param string $purchaserEmail
     * @param string $status
     * @return PurchaseOrderInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getPurchaseOrder(
        string $companyAdminEmail,
        string $purchaserEmail,
        string $status
    ): PurchaseOrderInterface {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $companyAdmin = $customerRepository->get($companyAdminEmail);
        $this->session->loginById($companyAdmin->getId());

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer($purchaserEmail);
        $purchaseOrder->setStatus($status);
        $purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $purchaseOrderRepository->save($purchaseOrder);
        self::assertNull($purchaseOrder->getOrderId());
        self::assertNull($purchaseOrder->getOrderIncrementId());

        return $purchaseOrder;
    }

    /**
     * Data provider of purchase order statuses that allow approval.
     *
     * @return array[]
     */
    public function convertablePurchaseOrderStatusDataProvider()
    {
        return [
            'Approved' => [PurchaseOrderInterface::STATUS_APPROVED],
            'Approved - Order Failed' => [PurchaseOrderInterface::STATUS_ORDER_FAILED]
        ];
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
