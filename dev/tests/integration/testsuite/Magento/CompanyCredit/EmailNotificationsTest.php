<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyCredit;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanySearchResultsInterface;
use Magento\CompanyCredit\Action\ReimburseFacade;
use Magento\CompanyCredit\Api\CreditLimitManagementInterface;
use Magento\CompanyCredit\Api\CreditLimitRepositoryInterface;
use Magento\CompanyCredit\Api\Data\CreditLimitInterfaceFactory;
use Magento\CompanyCredit\Model\CompanyCreditPaymentConfigProvider;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Mail\EmailMessage;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\TestCase;

/**
 * Test company credit changes email notifications.
 *
 * @magentoConfigFixture default/payment/companycredit/active 1
 * @magentoConfigFixture default_store carriers/freeshipping/active 1
 * @magentoDataFixture Magento/Catalog/_files/product_simple_tax_none.php
 * @magentoDataFixture Magento/Company/_files/company_with_admin.php
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class EmailNotificationsTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var CompanyInterface
     */
    private $company;

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @var TransportBuilderMock
     */
    protected $transportBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->company = $this->getCompany();
        $this->customer = $this->getCustomer();
        $this->product = $this->getProduct();
        $this->transportBuilder = $this->objectManager->get(TransportBuilderMock::class);
    }

    /**
     * Test company credit operations email notifications.
     *
     * @return void
     */
    public function testCompanyCreditNotifications(): void
    {
        // Check credit allocation message
        $creditAmount = '500.05';
        $this->setCompanyCredit((float)$creditAmount);
        $this->assertCompanyCreditAllocatedMessage('Allocation credit message is incorrect', $creditAmount);

        // Check company balance reimbursement message
        $reimbursementAmount = '89.07';
        $this->reimburseCompanyBalance((float)$reimbursementAmount);
        $this->assertCompanyBalanceReimbursementMessage(
            'Reimburse company balance message is incorrect',
            $reimbursementAmount
        );

        // Check credit revert message
        $order = $this->createCompanyCreditOrder(1);
        /** @var OrderManagementInterface $orderManagement */
        $orderManagement = $this->objectManager->get(OrderManagementInterface::class);
        $orderManagement->cancel($order->getEntityId());
        $this->assertCompanyCreditRevertMessage(
            'Company credit revert message is incorrect',
            $order->getIncrementId()
        );

        // Check company credit update message
        $creditAmount = '400.07';
        $this->setCompanyCredit((float)$creditAmount);
        $this->assertCompanyCreditUpdatedMessage(
            'Update company credit message is incorrect',
            $creditAmount,
            $reimbursementAmount
        );

        // Check refund ccompany credit message
        $order = $this->createCompanyCreditOrder(2);
        $this->createInvoiceFromOrder($order);
        $creditMemo = $this->createCreditMemoFromOrder($order);
        $this->assertCompanyCreditRefundedMessage(
            'Refund company credit message is incorrect',
            number_format($creditMemo->getGrandTotal(), 2),
            $order->getIncrementId()
        );
    }

    /**
     * Set company credit amount.
     *
     * @param float $creditAmount
     * @return void
     */
    private function setCompanyCredit(float $creditAmount): void
    {
        /** @var CreditLimitManagementInterface $creditLimitManagement */
        $creditLimitManagement = $this->objectManager->get(CreditLimitManagementInterface::class);
        /** @var CreditLimitInterfaceFactory $creditLimitFactory */
        $creditLimitFactory = $this->objectManager->get(CreditLimitInterfaceFactory::class);
        /** @var CreditLimitRepositoryInterface $creditLimitRepository */
        $creditLimitRepository = $this->objectManager->get(CreditLimitRepositoryInterface::class);
        /** @var \Magento\CompanyCredit\Api\Data\CreditLimitInterface $creditLimit */
        try {
            $creditLimit = $creditLimitManagement->getCreditByCompanyId($this->company->getId());
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $creditLimit = $creditLimitFactory->create();
            $creditLimit->setCompanyId($this->company->getId());
        }
        $creditLimit->setCurrencyCode('USD');
        $creditLimit->setCreditLimit($creditAmount);
        $creditLimit->setExceedLimit(false);
        $creditLimit->setCreditComment('Test purposes');
        $creditLimitRepository->save($creditLimit);
    }

    /**
     * Reimburse company balance.
     *
     * @param float $amount
     * @return void
     */
    private function reimburseCompanyBalance(float $amount): void
    {
        /** @var ReimburseFacade $reimburseFacade */
        $reimburseFacade = $this->objectManager->get(ReimburseFacade::class);
        $reimburseFacade->execute(
            $this->company->getId(),
            $amount,
            'Test reimburse',
            ''
        );
    }

    /**
     * Create new company order with companycredit payment method.
     *
     * @param float $qty
     * @return OrderInterface
     */
    private function createCompanyCreditOrder(float $qty): OrderInterface
    {
        $addressData = [
            'region' => 'CA',
            'region_id' => '12',
            'postcode' => '90210',
            'firstname' => $this->customer->getFirstname(),
            'lastname' => $this->customer->getLastname(),
            'street' => 'Brightom way',
            'city' => 'Beverly Hills',
            'email' => $this->customer->getEmail(),
            'telephone' => '1234567890',
            'country_id' => 'US',
        ];

        $billingAddress = $this->objectManager->create(
            Address::class,
            ['data' => $addressData]
        );
        $billingAddress->setAddressType('billing');

        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)
            ->setAddressType('shipping');

        /** @var \Magento\Store\Api\Data\StoreInterface $store */
        $store = $this->objectManager->get(StoreManagerInterface::class)->getStore();

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $quote->setCustomer($this->getCustomer())
            ->setStoreId($store->getId())
            ->setReservedOrderId('tsg-' . bin2hex(random_bytes(5)))
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->addProduct($this->product, $qty);

        $quote->getPayment()
            ->setMethod(CompanyCreditPaymentConfigProvider::METHOD_NAME);
        /** @var $rate \Magento\Quote\Model\Quote\Address\Rate */
        $rate = $this->objectManager->create(\Magento\Quote\Model\Quote\Address\Rate::class);
        $rate
            ->setCode('freeshipping_freeshipping')
            ->getPrice(1);
        $quote->getShippingAddress()->setShippingMethod('freeshipping_freeshipping');
        $quote->getShippingAddress()->addShippingRate($rate);

        /** @var CartRepositoryInterface $quoteRepository */
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quoteRepository->save($quote);
        /** @var CartManagementInterface $cartManagement */
        $cartManagement = $this->objectManager->get(CartManagementInterface::class);
        $orderId = $cartManagement->placeOrder($quote->getId());
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->objectManager->create(OrderRepositoryInterface::class);
        $order = $orderRepository->get($orderId);

        return $order;
    }

    /**
     * Create invoice from order.
     *
     * @param OrderInterface $order
     * @return InvoiceInterface
     */
    private function createInvoiceFromOrder(OrderInterface $order): InvoiceInterface
    {
        /** @var InvoiceManagementInterface $invoiceManagement */
        $invoiceManagement = $this->objectManager->create(
            InvoiceManagementInterface::class
        );
        $invoice = $invoiceManagement->prepareInvoice($order);
        $invoice->register();
        $order = $invoice->getOrder();
        $order->setIsInProcess(true);
        /** @var Transaction $transactionSave */
        $transactionSave = $this->objectManager->create(Transaction::class);
        $transactionSave->addObject($invoice)->addObject($order)->save();

        return $invoice;
    }

    /**
     * Create credit memo from order.
     *
     * @param OrderInterface $order
     * @return CreditmemoInterface
     */
    private function createCreditMemoFromOrder(OrderInterface $order): CreditmemoInterface
    {
        /** @var CreditmemoFactory $creditMemoFactory */
        $creditMemoFactory = $this->objectManager->get(CreditmemoFactory::class);

        /** @var CreditmemoInterface $creditmemo */
        $creditMemo = $creditMemoFactory->createByOrder($order, $order->getData());
        $creditMemo->setOrder($order);
        $creditMemo->setState(Creditmemo::STATE_OPEN);
        $creditMemo->setIncrementId($order->getIncrementId());

        /** @var CreditmemoService $creditMemoService */
        $creditMemoService = $this->objectManager->get(CreditmemoService::class);
        $creditMemoService->refund($creditMemo, true);

        return $creditMemo;
    }

    /**
     * Assert that company credit successfully allocated.
     *
     * @param string $errorMessage
     * @param string $amount
     * @return void
     */
    private function assertCompanyCreditAllocatedMessage(string $errorMessage, string $amount): void
    {
        /** @var EmailMessage $message */
        $message = $this->transportBuilder->getSentMessage();
        $this->assertEquals(
            sprintf('Credit allocated to %s', $this->company->getCompanyName()),
            $message->getSubject(),
            $errorMessage . ': Subject'
        );
        $this->assertStringContainsString(
            sprintf('Your company has been allocated a credit of $%s', $amount),
            $message->getBody()->getParts()[0]->getRawContent(),
            $errorMessage . ': Body'
        );
    }

    /**
     * Check message after company credit reimbursement.
     *
     * @param string $errorMessage
     * @param string $amount
     * @return void
     */
    private function assertCompanyBalanceReimbursementMessage(string $errorMessage, string $amount): void
    {
        /** @var EmailMessage $message */
        $message = $this->transportBuilder->getSentMessage();
        $this->assertEquals(
            sprintf('%s account reimbursed', $this->company->getCompanyName()),
            $message->getSubject(),
            $errorMessage . ': Subject'
        );
        $this->assertStringContainsString(
            sprintf('Your company credit account has been reimbursed by $%s.', $amount),
            $message->getBody()->getParts()[0]->getRawContent(),
            $errorMessage . ': Body'
        );
    }

    /**
     * Check company credit revert message after order cancellation.
     *
     * @param string $errorMessage
     * @param string $orderNumber
     * @return void
     */
    private function assertCompanyCreditRevertMessage(string $errorMessage, string $orderNumber): void
    {
        /** @var EmailMessage $message */
        $message = $this->transportBuilder->getSentMessage();
        $this->assertEquals(
            sprintf('Order %s reverted', $orderNumber),
            $message->getSubject(),
            $errorMessage . ': Subject'
        );
        $this->assertStringContainsString(
            sprintf(
                'Your order #%s has been cancelled. The order amount reverted to the company credit.',
                $orderNumber
            ),
            $message->getBody()->getParts()[0]->getRawContent(),
            $errorMessage . ': Body'
        );
    }

    /**
     * Assert that company credit successfully updated.
     *
     * @param string $errorMessage
     * @param string $amount
     * @param string $balance
     * @return void
     */
    private function assertCompanyCreditUpdatedMessage(string $errorMessage, string $amount, string $balance): void
    {
        /** @var EmailMessage $message */
        $message = $this->transportBuilder->getSentMessage();
        $this->assertEquals(
            sprintf('%s credit limit updated', $this->company->getCompanyName()),
            $message->getSubject(),
            $errorMessage . ': Subject'
        );

        $assertBody = $this->logicalAnd(
            new StringContains(
                sprintf('Your credit limit has been updated and is now $%s', $amount)
            ),
            new StringContains(
                sprintf('Your outstanding balance currently totals $%s.', $balance)
            )
        );

        $this->assertThat(
            $message->getBody()->getParts()[0]->getRawContent(),
            $assertBody,
            $errorMessage . ': Body'
        );
    }

    /**
     * Assert company credit refund message.
     *
     * @param string $errorMessage
     * @param string $amount
     * @param string $orderNumber
     */
    private function assertCompanyCreditRefundedMessage(string $errorMessage, string $amount, string $orderNumber): void
    {
        /** @var EmailMessage $message */
        $message = $this->transportBuilder->getSentMessage();
        $this->assertEquals(
            sprintf('Order %s refunded', $orderNumber),
            $message->getSubject(),
            $errorMessage . ': Subject'
        );

        $this->assertStringContainsString(
            sprintf('A refund of $%s has been issued on order #%s.', $amount, $orderNumber),
            $message->getBody()->getParts()[0]->getRawContent(),
            $errorMessage . ': Body'
        );
    }

    /**
     * Get current company.
     *
     * @return CompanyInterface
     */
    private function getCompany(): CompanyInterface
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter('company_email', 'support@example.com')
            ->create();
        /** @var CompanyRepositoryInterface $companyRepository */
        $companyRepository = $this->objectManager->get(CompanyRepositoryInterface::class);
        /** @var CompanySearchResultsInterface $companyList */
        $companyList = $companyRepository->getList($searchCriteria);
        $items = $companyList->getItems();
        $company = reset($items);

        return $company;
    }

    /**
     * Get current customer.
     *
     * @return CustomerInterface
     */
    private function getCustomer(): CustomerInterface
    {
        /** @var CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        /** @var CustomerInterface $customer */
        $customer = $customerRepository->get('company-admin@example.com');

        return $customer;
    }

    /**
     * Get product.
     *
     * @return ProductInterface
     */
    private function getProduct(): ProductInterface
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        /** @var ProductInterface $product */
        $product = $productRepository->get('simple-product-tax-none', false, null, true);

        return $product;
    }
}
