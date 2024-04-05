<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model\Validator;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleApproverRepositoryInterface;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test for validating Purchase Order
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RuleTest extends TestCase
{
    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var AppliedRuleRepositoryInterface
     */
    private $appliedRulesRepository;

    /**
     * @var AppliedRuleApproverRepositoryInterface
     */
    private $appliedRulesApproverRepository;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Rule
     */
    private $validatorRule;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->publisher = $this->objectManager->get(PublisherInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->appliedRulesRepository = $this->objectManager->get(AppliedRuleRepositoryInterface::class);
        $this->appliedRulesApproverRepository =
            $this->objectManager->get(AppliedRuleApproverRepositoryInterface::class);
        $this->publisher = $this->getMockBuilder(PublisherInterface::class)
            ->disableOriginalConstructor()->getMockForAbstractClass();
        $this->validatorRule = $this->objectManager->create(
            Rule::class,
            [
                'publisher' => $this->publisher
            ]
        );
    }

    /**
     * Test a rule which won't match the purchase order marks the order as approved at end of checkout
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_no_match.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testNoMatchApprovalRuleCallsValidation()
    {
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Expect the publisher to be called
        $this->publisher->expects($this->once())
            ->method('publish')
            ->with('purchaseorder.validation', $id);

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Validate the purchase order
        $this->validatorRule->validate($purchaseOrder);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_PENDING, $postPurchaseOrder->getStatus());
    }

    /**
     * Test a rule which won't match the purchase order marks the order as approved at end of checkout
     *
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_disabled.php
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testDisabledApprovalRuleAutoApprovesOrder()
    {
        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $id = $purchaseOrder->getEntityId();

        // Expect the publisher to be called
        $this->publisher->expects($this->never())
            ->method('publish');

        // Force the purchase order into pending state
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_PENDING);
        $this->purchaseOrderRepository->save($purchaseOrder);

        // Validate the purchase order
        $this->validatorRule->validate($purchaseOrder);

        // Verify the purchase order was approved, as it did not match rules
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($id);
        $this->assertEquals(PurchaseOrderInterface::STATUS_APPROVED, $postPurchaseOrder->getStatus());
    }

    /**
     * @param string $customerEmail
     * @return PurchaseOrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
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
