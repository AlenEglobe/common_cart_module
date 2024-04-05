<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Block\PurchaseOrder\Approval;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\View\LayoutInterface;
use Magento\PurchaseOrderRule\Block\PurchaseOrder\Approval\Counter;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CounterTest extends TestCase
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customerSession = Bootstrap::getObjectManager()->get(Session::class);
        $this->customerRepository = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule_one_manager_approved.php
     * @magentoAppArea frontend
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testPurchaseOrdersToApprovalCounterCheckOfManagers(): void
    {
        $this->loginCompanyCustomer('veronica.costello@example.com');
        $this->assertEmpty($this->obtainCounterBlock()->toHtml());

        $this->customerSession->logout();

        $this->loginCompanyCustomer('alex.smith@example.com');
        $this->checkCounterValue(1);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoAppArea frontend
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testNoPurchaseOrdersAwaitingForApprove(): void
    {
        $this->loginCompanyCustomer();

        $this->assertEmpty($this->obtainCounterBlock()->toHtml());
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_admin_approver.php
     * @magentoAppArea frontend
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testPurchaseOrdersAwaitingForApproveCount(): void
    {
        $this->loginCompanyCustomer();
        $this->checkCounterValue(1);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_several_purchase_order_admin_approver.php
     * @magentoAppArea frontend
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testSeveralPurchaseOrdersAwaitingForApproveCount(): void
    {
        $this->loginCompanyCustomer();
        $this->checkCounterValue(4);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule.php
     * @magentoAppArea frontend
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testPurchaseOrderAwaitingForApproveByManager(): void
    {
        $this->loginCompanyCustomer('veronica.costello@example.com');
        $this->checkCounterValue(1);
    }

    /**
     * Checking blocks counters value
     *
     * @param int $counterValue
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function checkCounterValue(int $counterValue): void
    {
        $html = $this->obtainCounterBlock()->toHtml();

        $matches = [];
        preg_match('/\<span.*\">(?<counter_value>\d)/', $html, $matches);

        $this->assertEquals($counterValue, (int)$matches['counter_value']);
    }

    /**
     * Method logs in company admin customer
     *
     * @param string $customerEmail
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function loginCompanyCustomer(string $customerEmail = 'john.doe@example.com'): void
    {
        $customer = $this->customerRepository->get($customerEmail);
        $this->customerSession->loginById($customer->getId());
    }

    /**
     * Method obtains counter block
     *
     * @return Counter
     */
    private function obtainCounterBlock(): Counter
    {
        /** @var LayoutInterface $layout */
        $layout = Bootstrap::getObjectManager()->get(LayoutInterface::class);

        $counterBlock = $layout->getBlock('counter');
        if (!$counterBlock) {
            $counterBlock = $layout->createBlock(Counter::class, 'counter');
            $counterBlock->setTemplate('Magento_PurchaseOrderRule::purchaseorder/tab_requires_approval_counter.phtml');
        }

        return $counterBlock;
    }
}
