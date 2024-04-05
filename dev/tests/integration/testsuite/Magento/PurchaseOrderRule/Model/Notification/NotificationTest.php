<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model\Notification;

use Magento\Company\Api\AclInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Notification\Action\RequestApproval;
use Magento\PurchaseOrder\Model\Notification\Action\ApprovalRequired;
use Magento\PurchaseOrder\Model\Notification\NotifierInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use Magento\TestFramework\Mail\TransportInterfaceMock;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;

/**
 * Notification email tests for PurchaseOrderRule module
 *
 * @see \Magento\PurchaseOrder\Model\Notification\ActionNotificationInterface
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NotificationTest extends TestCase
{
    private const CONSUMER_NAME = 'purchaseorder.transactional.email';

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var NotifierInterface
     */
    private $notifier;

    /**
     * @var ConsumerFactory
     */
    private $consumerFactory;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var AclInterface
     */
    private $companyAcl;

    /**
     * @var ClearQueueProcessor
     */
    private $clearQueueProcessor;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->consumerFactory = $this->objectManager->get(ConsumerFactory::class);
        $this->notifier = $this->objectManager->get(NotifierInterface::class);
        $this->companyAcl = $this->objectManager->get(AclInterface::class);
        $this->clearQueueProcessor = $this->objectManager->get(ClearQueueProcessor::class);

        // Enable company functionality at the system level
        $scopeConfig = $this->objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationNotSentForApprovedPurchaseOrder()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'john.doe@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, RequestApproval::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNull($sentMessage);
    }

    /**
     * If the rule that applies has two approver roles the result email should contain list of role names.
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule.php
     */
    public function testSingleRuleMultipleRoleUsersNotificationApprovalRequestMailContent()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $buyerEmail = 'buyer@example.com';
        $buyer = $this->customerRepository->get($buyerEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$buyer->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, ApprovalRequired::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString("Your Purchase Order requires approval from:", $messageRaw);
        $this->assertStringContainsString("Role 1</li>", $messageRaw);
        $this->assertStringContainsString("Role 2</li>", $messageRaw);
    }

    /**
     * If the approvers required are manager and company administrator, the naming in the email is correct.
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_manager_and_company_admin_approvers_single_rule.php
     */
    public function testNotificationApprovalRequestMailContentWithAdminMangerApprovers()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $buyerEmail = 'buyer@example.com';
        $buyer = $this->customerRepository->get($buyerEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$buyer->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, ApprovalRequired::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString("Your Purchase Order requires approval from:", $messageRaw);
        $this->assertStringContainsString("Your Company Administrator</li>", $messageRaw);
        $this->assertStringContainsString("Your Manager</li>", $messageRaw);
    }

    /**
     * If all rules that apply have empty roles the parent company user should be notified
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_role_no_user.php
     */
    public function testEmptyRoleNotificationApprovalRequest()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $transportBuilder = $this->getMockBuilder(TransportBuilderMock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateIdentifier')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateOptions')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateVars')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setFromByScope')
            ->willReturnSelf();
        // This verifies we've notified the admin
        $transportBuilder->expects($this->exactly(1))
            ->method('addTo')
            ->withConsecutive(
                ['john.doe@example.com', 'John Doe']
            )
            ->willReturnSelf();
        $transport = $this->getMockBuilder(TransportInterfaceMock::class)
            ->setMethods(['sendMessage'])
            ->getMock();
        $transportBuilder->expects($this->any())
            ->method('getTransport')
            ->willReturn($transport);
        $transport->expects($this->exactly(1))
            ->method('sendMessage');

        $this->objectManager->addSharedInstance($transportBuilder, TransportBuilderMock::class);

        $purchaserEmail = 'buyer@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, RequestApproval::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(2);
    }

    /**
     * Verify that a single applied rule sends an email
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_approver.php
     */
    public function testSingleNotificationApprovalRequest()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $transportBuilder = $this->getMockBuilder(TransportBuilderMock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateIdentifier')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateOptions')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateVars')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setFromByScope')
            ->willReturnSelf();
        // This verifies we've notified the single customer in the role
        $transportBuilder->expects($this->exactly(1))
            ->method('addTo')
            ->withConsecutive(
                ['veronica.costello@example.com', 'Veronica Costello']
            )
            ->willReturnSelf();
        $transport = $this->getMockBuilder(TransportInterfaceMock::class)
            ->setMethods(['sendMessage'])
            ->getMock();
        $transportBuilder->expects($this->any())
            ->method('getTransport')
            ->willReturn($transport);
        $transport->expects($this->exactly(1))
            ->method('sendMessage');

        $this->objectManager->addSharedInstance($transportBuilder, TransportBuilderMock::class);

        $purchaserEmail = 'buyer@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, RequestApproval::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(2);
    }

    /**
     * Verify that a manager role rule sends an email to the immediate manager of the PO creator
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_single_manager_approver.php
     */
    public function testImmediateManagerNotificationApprovalRequest()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $transportBuilder = $this->getMockBuilder(TransportBuilderMock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateIdentifier')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateOptions')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setTemplateVars')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(1))
            ->method('setFromByScope')
            ->willReturnSelf();
        // This verifies we've notified the buyer's immediate manager
        $transportBuilder->expects($this->exactly(1))
            ->method('addTo')
            ->withConsecutive(
                ['veronica.costello@example.com', 'Veronica Costello']
            )
            ->willReturnSelf();
        $transport = $this->getMockBuilder(TransportInterfaceMock::class)
            ->setMethods(['sendMessage'])
            ->getMock();
        $transportBuilder->expects($this->any())
            ->method('getTransport')
            ->willReturn($transport);
        $transport->expects($this->exactly(1))
            ->method('sendMessage');

        $this->objectManager->addSharedInstance($transportBuilder, TransportBuilderMock::class);

        $purchaserEmail = 'alex.smith@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, RequestApproval::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(2);
    }

    /**
     * Verify that rules with overlapping approvers sends only one email
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_overlapping_approvers.php
     */
    public function testSingleNotificationForOverlappingApproversRequest()
    {
        $this->testImmediateManagerNotificationApprovalRequest();
    }

    /**
     * Verify that both emails are sent from each of the 2 applied rules individual roles
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_multiple_rules.php
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testMultipleRulesNotificationApprovalRequest()
    {
        $this->verifyTwoEmailsDispatched();
    }

    /**
     * Verify that two emails are dispatched for the two users within the single applied rule role
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_single_rule.php
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSingleRuleMultipleRoleUsersNotificationApprovalRequest()
    {
        $this->verifyTwoEmailsDispatched();
    }

    /**
     * Verify that two emails are dispatched for the two roles users within the single applied rule
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_roles_single_rule.php
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSingleRuleMultipleRolesNotificationApprovalRequest()
    {
        $this->verifyTwoEmailsDispatched();
    }

    /**
     * Verify that two emails are dispatched to the level one and level two users
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function verifyTwoEmailsDispatched()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $transportBuilder = $this->getMockBuilder(TransportBuilderMock::class)
            ->disableOriginalConstructor()
            ->getMock();
        $transportBuilder->expects($this->exactly(2))
            ->method('setTemplateIdentifier')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(2))
            ->method('setTemplateOptions')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(2))
            ->method('setTemplateVars')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(2))
            ->method('setFromByScope')
            ->willReturnSelf();
        $transportBuilder->expects($this->exactly(2))
            ->method('addTo')
            ->withConsecutive(
                ['veronica.costello@example.com', 'Veronica Costello'],
                ['alex.smith@example.com', 'Alex Smith']
            )
            ->willReturnSelf();
        $transport = $this->getMockBuilder(TransportInterfaceMock::class)
            ->setMethods(['sendMessage'])
            ->getMock();
        $transportBuilder->expects($this->any())
            ->method('getTransport')
            ->willReturn($transport);
        $transport->expects($this->exactly(2))
            ->method('sendMessage');

        $this->objectManager->addSharedInstance($transportBuilder, TransportBuilderMock::class);

        $purchaserEmail = 'buyer@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, RequestApproval::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(2);
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param int $customerId
     * @return PurchaseOrderInterface
     */
    private function getCustomerFirstPurchaseOrder(int $customerId)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customerId)
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }

    /**
     * Verify that approval request only sent to users, who are not PO creator.
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/company_with_purchase_order_multiple_approvers_single_rule.php
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testSingleRuleMultipleApproversExcludingCreatorNotified()
    {
        // Adding buyer to the approvers role, so role has 3 users assigned to it
        $buyerCustomer = $this->customerRepository->get('buyer@example.com');
        $approverCustomer = $this->customerRepository->get('alex.smith@example.com');
        $this->companyAcl->assignRoles(
            $buyerCustomer->getId(),
            $this->companyAcl->getRolesByUserId($approverCustomer->getId())
        );
        $this->verifyTwoEmailsDispatched();
    }
}
