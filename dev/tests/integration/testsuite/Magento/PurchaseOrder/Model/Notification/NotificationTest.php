<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Model\Notification;

use Laminas\Stdlib\Parameters;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\Notification\Action\ApprovalAndPaymentDetailsRequired;
use Magento\PurchaseOrder\Model\Notification\Action\ApprovalRequired;
use Magento\PurchaseOrder\Model\Notification\Action\Approved;
use Magento\PurchaseOrder\Model\Notification\Action\AutoApproved;
use Magento\PurchaseOrder\Model\Notification\Action\AutoApprovedPendingPayment;
use Magento\PurchaseOrder\Model\Notification\Action\CommentAdded;
use Magento\PurchaseOrder\Model\Notification\Action\OrderPlacementFailed;
use Magento\PurchaseOrder\Model\Notification\Action\Rejected;
use Magento\PurchaseOrder\Model\Notification\Action\RequestApproval;
use Magento\PurchaseOrder\Model\Notification\Notifier\QueueMessageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use Magento\TestFramework\MessageQueue\ClearQueueProcessor;
use Magento\TestFramework\TestCase\AbstractController as AbstractTestCase;
use Magento\Framework\MessageQueue\PublisherInterface;

/**
 * Notification email test.
 *
 * @see \Magento\PurchaseOrder\Model\Notification\ActionNotificationInterface
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class NotificationTest extends AbstractTestCase
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
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var NotifierInterface
     */
    private $notifier;

    /**
     * @var ConsumerFactory
     */
    private $consumerFactory;

    /**
     * @var QueueMessageFactory
     */
    private $queueMessageFactory;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;

    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    private $configResource;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\App\Config
     */
    private $appConfig;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var PublisherInterface
     */
    private $publisher;

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
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $this->objectManager->get(Session::class);
        $this->consumerFactory = $this->objectManager->get(ConsumerFactory::class);
        $this->queueMessageFactory = $this->objectManager->get(QueueMessageFactory::class);
        $this->notifier = $this->objectManager->get(NotifierInterface::class);
        $this->companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        $this->dateTime = $this->objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class);
        $this->configResource = $this->objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->appConfig = $this->objectManager->get(\Magento\Framework\App\Config::class);
        $this->roleRepository = $this->objectManager->get(RoleRepositoryInterface::class);
        $this->urlBuilder = $this->objectManager->get(\Magento\Framework\UrlInterface::class);
        $this->publisher = $this->objectManager->get(PublisherInterface::class);
        $this->clearQueueProcessor = $this->objectManager->get(ClearQueueProcessor::class);

        // Enable company functionality at the system level
        $scopeConfig = $this->objectManager->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue('btob/website_configuration/company_active', '1', ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Data provider for testEmailTemplatesReplaced
     *
     * @return array
     */
    public function emailReplaceTemplateDataProvider()
    {
        return [
            [
                'sales_email/purchase_order_notification/purchase_order_approval_request',
                'sales_email_purchase_order_notification_purchase_order_approval_request'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_approval_required',
                'sales_email_purchase_order_notification_purchase_order_approval_required'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_approval_required_payment_details',
                'sales_email_purchase_order_notification_purchase_order_approval_required_payment_details'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_auto_approved',
                'sales_email_purchase_order_notification_purchase_order_auto_approved'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_approved_payment_details',
                'sales_email_purchase_order_notification_purchase_order_approved_payment_details'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_auto_approved_payment_details',
                'sales_email_purchase_order_notification_purchase_order_auto_approved_payment_details'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_approved',
                'sales_email_purchase_order_notification_purchase_order_approved'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_rejected',
                'sales_email_purchase_order_notification_purchase_order_rejected'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_comment_added',
                'sales_email_purchase_order_notification_purchase_order_comment_added'
            ],
            [
                'sales_email/purchase_order_notification/purchase_order_order_place_failed',
                'sales_email_purchase_order_notification_purchase_order_order_place_failed'
            ]
        ];
    }

    /**
     * Test email templates redefinition in config.
     *
     * @param $configPath
     * @param $templateCode
     * @throws \Exception
     * @dataProvider emailReplaceTemplateDataProvider
     * @magentoDbIsolation enabled
     */
    public function testEmailTemplatesReplaced($configPath, $templateCode)
    {
        /** @var \Magento\Email\Model\BackendTemplate $templateModel */
        $templateModel = $this->objectManager->create(\Magento\Email\Model\BackendTemplate::class);
        $templateModel->setTemplateSubject(
            'Updated Template'
        )->setTemplateCode(
            'Updated Template Code'
        )->setTemplateText(
            '---Updated template content.---'
        )->setTemplateStyles(
            ''
        )->setModifiedAt(
            $this->dateTime->gmtDate()
        )->setOrigTemplateCode(
            $templateCode
        )->setOrigTemplateVariables(
            '{}'
        );
        $templateModel->save();
        $this->configResource->saveConfig(
            $configPath,
            $templateModel->getId(),
            'default',
            $this->storeManager->getDefaultStoreView()->getId()
        );
        //clean cached config
        $this->appConfig->clean();
        $this->assertEquals(
            $templateModel->getId(),
            $this->appConfig->getValue($configPath)
        );
        $templateModel->delete();
    }

    /**
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 1
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationRequestApproval()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, RequestApproval::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $incrementId = $purchaseOrder->getIncrementId();
        $this->assertEquals(
            "Purchase Order #$incrementId is ready for your approval",
            $sentMessage->getSubject()
        );
        $this->assertNotNull($sentMessage);
        $this->assertEquals('John Doe', $sentMessage->getTo()[0]->getName());
        $this->assertEquals('john.doe@example.com', $sentMessage->getTo()[0]->getEmail());
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            "{$purchaser->getFirstname()} {$purchaser->getLastname()} placed a Purchase Order "
            . "that requires your approval",
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationAutoApproved()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, AutoApproved::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            'has been created and approved automatically.',
            $messageRaw
        );
        $this->assertStringContainsString(
            'You will receive an e-mail with your Order confirmation shortly.',
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * Test the reward points are showing on PO auto approved email totals block
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_reward_points.php
     */
    public function testNotificationAutoApprovedRewardPointsTotals()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'customer@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, AutoApproved::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            'has been created and approved automatically.',
            $messageRaw
        );
        $this->assertStringContainsString(
            'You will receive an e-mail with your Order confirmation shortly.',
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by John Smith',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM',
            'address_country' => 'United States',
            'item_name' => 'Simple Product',
            'item_sku' => 'SKU: simple-1',
            'reward_points' => '5 Reward points',
            'grand_total' => '$10.00'
        ]);
    }

    /**
     * Test the customer balance and gift cards are showing on PO auto approved email totals block
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders_with_customer_balance_and_gift_card.php
     */
    public function testNotificationAutoApprovedCustomerBalanceAndGiftCardsTotals()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'customer@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, AutoApproved::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            'has been created and approved automatically.',
            $messageRaw
        );
        $this->assertStringContainsString(
            'You will receive an e-mail with your Order confirmation shortly.',
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by John Smith',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'gift_card_label' => 'Gift Card (giftcardaccount_fixture)',
            'gift_card_value' => '-<span class="price" style="white-space: nowrap;">$9.99</span>',
            'customer_balance_label' => 'Store Credit',
            'customer_balance_value' => '-$0.01',
            'grand_total' => '$0.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationAutoApprovedPaymentDetails()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrder->setPaymentMethod('paypal_express');
        $quote = $purchaseOrder->getSnapshotQuote();
        $quote->getPayment()->setMethod('paypal_express');
        $purchaseOrder->setSnapshotQuote($quote);
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT);
        $this->purchaseOrderRepository->save($purchaseOrder);
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, AutoApprovedPendingPayment::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            'has been created and approved automatically.',
            $messageRaw
        );
        $this->assertStringContainsString(
            'Go to the Add Payment page to complete the order.',
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'PayPal Express Checkout',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * Test notification if disabled in current store.
     *
     * @magentoConfigFixture current_store sales_email/purchase_order_notification/enabled 0
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider purchaseOrderActionDataProvider
     */
    public function testNotificationDisabledInStorePurchaseOrderAction($actionClass)
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, $actionClass);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNull($sentMessage);
    }

    /**
     * Purchase order action notifications classes data provider.
     *
     * @return array
     */
    public function purchaseOrderActionDataProvider()
    {
        return [
            [ApprovalRequired::class],
            [Approved::class],
            [AutoApproved::class],
            [OrderPlacementFailed::class],
            [Rejected::class],
            [RequestApproval::class],
            [CommentAdded::class]
        ];
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationApprovedAction()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $approverEmail = 'john.doe@example.com';
        // Log in as the approver
        $approver = $this->customerRepository->get($approverEmail);
        $this->session->loginById($approver->getId());

        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());

        $purchaseOrderId = $purchaseOrder->getEntityId();
        $purchaseOrderIncId = $purchaseOrder->getIncrementId();

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('purchaseorder/purchaseorder/approve/request_id/' . $purchaseOrder->getEntityId());

        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertEquals($sentMessage->getSubject(), "Purchase Order #{$purchaseOrderIncId} has been Approved");
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $purchaseOrderUrl = $this->urlBuilder->getUrl()
            . "purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/";
        $this->assertStringContainsString(
            "Your Purchase Order <a href=\"{$purchaseOrderUrl}\" style=\"color: #006bb4; text-decoration: none;\">"
            . "#{$purchaseOrderIncId}</a> has been approved by "
            . "{$approver->getFirstname()} {$approver->getLastname()}.",
            $messageRaw
        );
        $this->assertStringContainsString(
            "You will receive an e-mail with your Order confirmation shortly.",
            $messageRaw
        );
        $this->assertStringContainsString("Your Purchase Order details are below.", $messageRaw);

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_with_purchase_orders_and_online_payment_method_used.php
     */
    public function testNotificationApprovedPendingPaymentDetails()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $approverEmail = 'john.doe@example.com';

        $approver = $this->customerRepository->get($approverEmail);
        $this->session->loginById($approver->getId());

        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $this->purchaseOrderRepository->save($purchaseOrder);

        $purchaseOrderId = $purchaseOrder->getEntityId();
        $purchaseOrderIncId = $purchaseOrder->getIncrementId();

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('purchaseorder/purchaseorder/approve/request_id/' . $purchaseOrder->getEntityId());
        $this->notifier->notifyOnAction((int)$purchaseOrderId, Approved::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertEquals($sentMessage->getSubject(), "Purchase Order #{$purchaseOrderIncId} has been Approved");
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $purchaseOrderUrl = $this->urlBuilder->getUrl()
            . "purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/";
        $this->assertStringContainsString(
            "Your Purchase Order <a href=\"{$purchaseOrderUrl}\" style=\"color: #006bb4; text-decoration: none;\">"
            . "#{$purchaseOrderIncId}</a> has been approved by "
            . "{$approver->getFirstname()} {$approver->getLastname()}.",
            $messageRaw
        );
        $this->assertStringContainsString(
            "Go to the Add Payment page to complete the order.",
            $messageRaw
        );
        $this->assertStringContainsString("Add Payment", $messageRaw);
        $this->assertStringContainsString("Your Purchase Order details are below.", $messageRaw);

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'PayPal Express Checkout',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationRejectAction()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $rejecterEmail = 'john.doe@example.com';
        // Log in as the rejecter
        $rejecter = $this->customerRepository->get($rejecterEmail);
        $this->session->loginById($rejecter->getId());

        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());

        $purchaseOrderId = $purchaseOrder->getEntityId();

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('purchaseorder/purchaseorder/reject/request_id/' . $purchaseOrder->getEntityId());

        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            "rejected by {$rejecter->getFirstname()} {$rejecter->getLastname()}",
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationPlaceOrderFailAction()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'veronica.costello@example.com';
        $approverEmail = 'john.doe@example.com';
        // Log in as the approver
        $approver = $this->customerRepository->get($approverEmail);
        $this->session->loginById($approver->getId());

        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrder->setStatus(PurchaseOrderInterface::STATUS_APPROVED);
        $purchaseOrder->setApprovedBy([$approver->getId()]);

        // Make sure the sales order conversion fails
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        $quote->removeAllItems();
        $this->quoteRepository->save($quote);
        $purchaseOrder->setSnapshotQuote($quote);

        $this->purchaseOrderRepository->save($purchaseOrder);

        $purchaseOrderId = $purchaseOrder->getEntityId();

        // Dispatch the request
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch('purchaseorder/purchaseorder/placeorder/request_id/' . $purchaseOrderId);

        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString(
            'was approved but an error occurred when converting it to an Order.',
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Veronica Costello',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @magentoConfigFixture default/btob/website_configuration/purchaseorder_enabled 1
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testNotificationCommentAction()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $companyAdmin = $this->customerRepository->get('admin@magento.com');
        $purchaser = $this->customerRepository->get('customer@example.com');

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->enablePurchaseOrdersByCompanyId(
            (int)$companyAdmin->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();

        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);

        /**
         * Dispatch Comment Request and run Queue Consumer
         *
         * @param string $comment
         */
        $dispatchCommentRequestAndRunQueueConsumer = function ($comment) use ($purchaseOrderId) {
            $request = $this->getRequest();

            $request
                ->setPost(new Parameters([])) // clear form_key from previous dispatches
                ->setMethod(Http::METHOD_POST)
                ->setParam('comment', $comment);

            $this->dispatch('purchaseorder/purchaseorder/addComment/request_id/' . $purchaseOrderId);

            $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
            $consumer->process(1);
        };

        // login as purchaser and submit comment
        $this->session->loginById($purchaser->getId());
        $dispatchCommentRequestAndRunQueueConsumer('First comment (from purchaser)');

        // assert no notification sent when purchaser comments first
        $this->assertNull($transportBuilderMock->getSentMessage());

        // login as company admin and submit comment on purchase order
        $this->session->loginById($companyAdmin->getId());
        $dispatchCommentRequestAndRunQueueConsumer('Second comment (from company admin)');

        // assert purchaser receives notification
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertEquals(
            "Comment added to Purchase Order #{$purchaseOrder->getIncrementId()}",
            $sentMessage->getSubject()
        );
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals(
            $purchaser->getEmail(),
            $sentMessage->getTo()[0]->getEmail()
        );
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString('A comment was added to Purchase Order', $messageRaw);
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        // assert notification greets purchaser
        $this->assertStringContainsString("{$purchaser->getFirstname()} {$purchaser->getLastname()},", $messageRaw);
        // assert notification denotes company admin as author
        $this->assertStringContainsString(
            "by {$companyAdmin->getFirstname()} {$companyAdmin->getLastname()}:",
            $messageRaw
        );
        $this->assertStringContainsString('Second comment (from company admin)', $messageRaw);

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by John Smith',
            'payment_method' => 'Check / Money order',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);

        // login as purchaser again and submit another comment
        $this->session->loginById($purchaser->getId());
        $dispatchCommentRequestAndRunQueueConsumer('Third comment (from purchaser)');

        // assert company admin receives notification
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertEquals(
            "Comment added to Purchase Order #{$purchaseOrder->getIncrementId()}",
            $sentMessage->getSubject()
        );
        $this->assertEquals(
            $companyAdmin->getFirstname() . ' ' . $companyAdmin->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals(
            $companyAdmin->getEmail(),
            $sentMessage->getTo()[0]->getEmail()
        );
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringContainsString('A comment was added to Purchase Order', $messageRaw);
        $this->assertStringContainsString((string)$purchaseOrder->getIncrementId(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        // assert notification greets company admin
        $this->assertStringContainsString(
            "{$companyAdmin->getFirstname()} {$companyAdmin->getLastname()},",
            $messageRaw
        );
        // assert notification denotes purchaser as author
        $this->assertStringContainsString("by {$purchaser->getFirstname()} {$purchaser->getLastname()}:", $messageRaw);
        $this->assertStringContainsString('Third comment (from purchaser)', $messageRaw);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider paymentMethodsDataProvider
     * @param string $paymentMethodCode
     * @param string $paymentMethodTitle
     */
    public function testNotificationApprovalRequired(string $paymentMethodCode, string $paymentMethodTitle)
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'alex.smith@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $this->applyPaymentMethodToPurchaseOrder($purchaseOrder, $paymentMethodCode);
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $incrementId = $purchaseOrder->getIncrementId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, ApprovalRequired::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertEquals(
            "Purchase Order #$incrementId has been Created and has been sent for approval",
            $sentMessage->getSubject()
        );
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $this->assertStringContainsString((string)$incrementId, $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString("is currently being processed.", $messageRaw);
        $this->assertStringContainsString(
            "Your Purchase Order requires approval from:",
            $messageRaw
        );
        $this->assertStringContainsString(
            "Veronica Costello",
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Alex Smith',
            'payment_method' => $paymentMethodTitle,
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_with_purchase_orders_and_online_payment_method_used.php
     */
    public function testNotificationApprovalRequiredPaymentDetails()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'alex.smith@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrder->setPaymentMethod('paypal_express');
        $this->purchaseOrderRepository->save($purchaseOrder);
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $incrementId = $purchaseOrder->getIncrementId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, ApprovalAndPaymentDetailsRequired::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertEquals(
            "Purchase Order #$incrementId has been Created and has been sent for approval",
            $sentMessage->getSubject()
        );
        $this->assertEquals(
            $purchaser->getFirstname() . ' ' . $purchaser->getLastname(),
            $sentMessage->getTo()[0]->getName()
        );
        $this->assertEquals($purchaserEmail, $sentMessage->getTo()[0]->getEmail());
        $this->assertStringContainsString((string)$incrementId, $messageRaw);
        $this->assertStringContainsString($purchaser->getFirstname() . ' ' . $purchaser->getLastname(), $messageRaw);
        $this->assertStringContainsString(
            "/purchaseorder/purchaseorder/view/request_id/{$purchaseOrderId}/",
            $messageRaw
        );
        $this->assertStringContainsString("is currently being processed.", $messageRaw);
        $this->assertStringContainsString(
            "Your Purchase Order requires approval from:",
            $messageRaw
        );
        $this->assertStringContainsString(
            "Veronica Costello",
            $messageRaw
        );
        $this->assertStringContainsString(
            "You will be asked to enter your payment details after the purchase order has been approved.",
            $messageRaw
        );

        $this->assertPurchaseOrderDetails($messageRaw, [
            'title' => "Purchase Order #{$purchaseOrder->getIncrementId()}",
            'created_by' => 'by Alex Smith',
            'payment_method' => 'PayPal Express Checkout',
            'address_line1' => 'Green str, 67',
            'address_line2' => 'CityM,  Alabama, 75477',
            'address_country' => 'United States',
            'item_name' => 'Virtual Product',
            'item_sku' => 'SKU: virtual-product',
            'grand_total' => '10.00'
        ]);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testNotificationApprovalNotRequiredPaymentDetails()
    {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaserEmail = 'alex.smith@example.com';
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrder->setPaymentMethod('checkmo');
        $this->purchaseOrderRepository->save($purchaseOrder);
        $purchaseOrderId = $purchaseOrder->getEntityId();
        $this->notifier->notifyOnAction((int)$purchaseOrderId, ApprovalRequired::class);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $this->assertStringNotContainsString(
            "You will be asked to enter your payment details after the purchase order has been approved.",
            $messageRaw
        );
        $this->assertStringNotContainsString(
            "Once the purchase order has been approved, it will be processed immediately.",
            $messageRaw
        );
    }

    /**
     * Apply the payment method to the provided purchase order.
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @param string $paymentMethodCode
     */
    private function applyPaymentMethodToPurchaseOrder(PurchaseOrderInterface $purchaseOrder, string $paymentMethodCode)
    {
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());
        $quote->getPayment()->setMethod($paymentMethodCode);
        $this->quoteRepository->save($quote);

        $purchaseOrder->setSnapshotQuote($quote);
        $purchaseOrder->setPaymentMethod($paymentMethodCode);
        $this->purchaseOrderRepository->save($purchaseOrder);
    }

    /**
     * Payment methods data provider.
     *
     * @return array
     */
    public function paymentMethodsDataProvider()
    {
        return [
            'offline_payment_method' => [
                'payment_method_code' => 'checkmo',
                'payment_method_title' => 'Check / Money order'
            ],
            'online_payment_method' => [
                'payment_method_code' => 'paypal_express',
                'payment_method_title' => 'PayPal Express Checkout'
            ]
        ];
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider negotiableQuoteMessageDataProvider
     * @param string $purchaserEmail
     * @param string $actionNotificationClass
     * @param string $expectedMessage
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testNotificationWithNegotiableQuote(
        string $purchaserEmail,
        string $actionNotificationClass,
        string $expectedMessage
    ) {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        // Get the Purchase Order details
        $purchaser = $this->customerRepository->get($purchaserEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $purchaseOrderId = $purchaseOrder->getEntityId();

        // Simulate that the Purchase Order was created from a Negotiable Quote
        $negotiableQuote = $this->simulatePurchaseOrderCreatedFromNegotiableQuote($purchaseOrder);
        $quoteId = $negotiableQuote->getQuoteId();
        $quoteName = $negotiableQuote->getQuoteName();

        // Send the notification
        $this->notifier->notifyOnAction((int)$purchaseOrderId, $actionNotificationClass);
        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);
        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();

        // Assert that the email was successfully sent
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();

        // Assert that the email body is updated to reflect the Negotiable Quote
        $this->assertStringContainsString("has been created based on your quote", $messageRaw);
        $this->assertStringContainsString("/negotiable_quote/quote/view/quote_id/{$quoteId}/", $messageRaw);
        $this->assertStringContainsString($quoteName, $messageRaw);
        $this->assertStringContainsString($expectedMessage, $messageRaw);
    }

    /**
     * @return array
     */
    public function negotiableQuoteMessageDataProvider()
    {
        return [
            'po_approval_required' => [
                'purchaser_email' => 'alex.smith@example.com',
                'action_notification_class' => ApprovalRequired::class,
                'expected_message' => 'and is being processed.'
            ],
            'po_auto_approved' => [
                'purchaser_email' => 'john.doe@example.com',
                'action_notification_class' => AutoApproved::class,
                'expected_message' => 'This Purchase Order has been approved automatically.'
            ]
        ];
    }

    /**
     * Verify purchase order auto approved notifications with passed rule for non company-admin user
     *
     * @dataProvider purchaseOrderAutoApprovedRulesBasedDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule_number_of_skus_more_than_3.php
     *
     * @param string $customerEmail
     * @param string $paymentMethodCode
     * @param string $expectedStatus
     * @param array $expectedEmailDetails
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testNotificationAutoApprovedRulesBased(
        string $customerEmail,
        string $paymentMethodCode,
        string $expectedStatus,
        array $expectedEmailDetails
    ) {
        $this->clearQueueProcessor->execute(self::CONSUMER_NAME);
        $purchaser = $this->customerRepository->get($customerEmail);
        $purchaseOrder = $this->getCustomerFirstPurchaseOrder((int)$purchaser->getId());
        $this->applyPaymentMethodToPurchaseOrder($purchaseOrder, $paymentMethodCode);
        $purchaseOrderId = $purchaseOrder->getEntityId();

        // Run the validation engine against the purchase order
        $this->publisher->publish('purchaseorder.validation', $purchaseOrderId);
        $consumer = $this->consumerFactory->get('purchaseorder.validation');
        $consumer->process(1);

        // Verify the purchase order requires approval and matched the shipping cost rule
        $postPurchaseOrder = $this->purchaseOrderRepository->getById($purchaseOrderId);
        $this->assertEquals($expectedStatus, $postPurchaseOrder->getStatus());

        $consumer = $this->consumerFactory->get(self::CONSUMER_NAME);
        $consumer->process(1);

        /** @var TransportBuilderMock $transportBuilderMock */
        $transportBuilderMock = $this->objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilderMock->getSentMessage();
        $this->assertNotNull($sentMessage);
        $messageRaw = $sentMessage->getBody()->getParts()[0]->getRawContent();
        $expectedEmailDetails[] = "Purchase Order #{$purchaseOrder->getIncrementId()}";
        $this->assertPurchaseOrderDetails($messageRaw, $expectedEmailDetails);
    }

    /**
     * DataProvider for purchase order auto approved rules based scenarios
     *
     * @return array[]
     */
    public function purchaseOrderAutoApprovedRulesBasedDataProvider()
    {
        return [
            'offline_payment_method' => [
                'customer_email' => 'alex.smith@example.com',
                'payment_method_code' => 'checkmo',
                'expected_status' => PurchaseOrderInterface::STATUS_APPROVED,
                'expected_email_details' => [
                    'content' => 'has been created and approved automatically.',
                    'content_additional' => 'You will receive an e-mail with your Order confirmation shortly.'
                ]
            ],
            'online_payment_method' => [
                'customer_email' => 'alex.smith@example.com',
                'payment_method_code' => 'paypal_express',
                'expected_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'expected_email_details' => [
                    'content' => 'has been created and approved automatically.',
                    'content_additional' => 'Go to the Add Payment page to complete the order'
                ]
            ]
        ];
    }

    /**
     * Assert that the Purchase Order details for the email notification are accurate.
     *
     * @param string $messageRaw
     * @param array $expectedValues
     */
    private function assertPurchaseOrderDetails($messageRaw, $expectedValues)
    {
        foreach ($expectedValues as $expectedValue) {
            $this->assertStringContainsString($expectedValue, $messageRaw);
        }
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
     * Enable Purchase Orders For Company
     *
     * @param int $companyId
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    private function enablePurchaseOrdersByCompanyId(int $companyId)
    {
        $company = $this->companyRepository->get($companyId);
        $company->getExtensionAttributes()->setIsPurchaseOrderEnabled(true);
        $this->companyRepository->save($company);
    }

    /**
     * Sets the permission value for the specified company role.
     *
     * @param string $companyName
     * @param string $roleName
     * @param string $resourceId
     * @param string $permissionValue
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function setCompanyRolePermission(
        string $companyName,
        string $roleName,
        string $resourceId,
        string $permissionValue
    ) {
        // Get the company
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        // Get the company role
        $this->searchCriteriaBuilder->addFilter('company_id', $company->getId());
        $this->searchCriteriaBuilder->addFilter('role_name', $roleName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->roleRepository->getList($searchCriteria)->getItems();

        /** @var RoleInterface $role */
        $role = reset($results);

        // For that role, find the specified permission and set it to the desired value
        foreach ($role->getPermissions() as $permission) {
            if ($permission->getResourceId() === $resourceId) {
                $permission->setPermission($permissionValue);
                break;
            }
        }

        $this->roleRepository->save($role);
    }

    /**
     * Simulate the creation of a Purchase Order from a Negotiable quote.
     *
     * @param PurchaseOrderInterface $purchaseOrder
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @return NegotiableQuoteInterface
     */
    private function simulatePurchaseOrderCreatedFromNegotiableQuote(
        PurchaseOrderInterface $purchaseOrder
    ) {
        $quote = $this->quoteRepository->get($purchaseOrder->getQuoteId());

        // Set the values for the Negotiable Quote extension attribute
        /** @var NegotiableQuoteInterface $negotiableQuote */
        $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();
        $negotiableQuote->setQuoteId($quote->getId())
            ->setQuoteName('Test Quote')
            ->setCreatorId($quote->getCustomer()->getId())
            ->setCreatorType(UserContextInterface::USER_TYPE_CUSTOMER)
            ->setIsRegularQuote(true)
            ->setNegotiatedPriceType(NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT)
            ->setNegotiatedPriceValue(10)
            ->setStatus(NegotiableQuoteInterface::STATUS_CREATED);
        $this->quoteRepository->save($quote);

        // Recalculate the quote totals using the negotiated discount
        /** @var NegotiableQuoteManagementInterface $negotiableQuoteManagement */
        $negotiableQuoteManagement = $this->objectManager->get(NegotiableQuoteManagementInterface::class);
        $negotiableQuoteManagement->recalculateQuote($quote->getId(), true);

        // Simulate approval by the merchant
        $negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN);
        $negotiableQuote->save();

        // Reload the quote with the updated pricing
        $quote = $this->quoteRepository->get($quote->getId());

        // Update the snapshot quote on the purchase order
        $purchaseOrder->setSnapshotQuote($quote);
        $this->purchaseOrderRepository->save($purchaseOrder);

        return $quote->getExtensionAttributes()->getNegotiableQuote();
    }
}
