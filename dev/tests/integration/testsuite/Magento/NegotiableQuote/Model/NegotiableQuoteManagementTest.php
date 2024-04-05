<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Test\Fixture\Company as CompanyFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\NegotiableQuote\Api\Data\HistoryInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Model\Email\Sender;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuote as NegotiableQuoteFixture;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Test\Fixture\User as UserFixture;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NegotiableQuoteManagementTest extends TestCase
{
    /**
     * @var NegotiableQuoteManagementInterface
     */
    private $negotiableQuoteManagement;
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var EmailSenderInterface&MockObject
     */
    private $emailSenderMock;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->emailSenderMock  = $this->getMockBuilder(Sender::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectManager->addSharedInstance($this->emailSenderMock, Sender::class);
        $this->negotiableQuoteManagement =
            $this->objectManager->create(NegotiableQuoteManagementInterface::class);
    }

    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(UserFixture::class, as: 'user'),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            NegotiableQuoteFixture::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_CREATED,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 5,
            ],
            'negotiable_quote'
        ),
    ]
    /**
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testAdminSend()
    {
        $negotiableQuoteId = DataFixtureStorageManager::getStorage()->get('negotiable_quote')->getId();

        // confirm that email notification was sent
        $this->emailSenderMock->expects($this->once())
            ->method('sendChangeQuoteEmailToBuyer')
            ->with(
                $this->callback(
                    function ($quote) use ($negotiableQuoteId) {
                        return $quote->getId() === $negotiableQuoteId;
                    }
                ),
                Sender::XML_PATH_BUYER_QUOTE_UPDATED_BY_SELLER_TEMPLATE
            );
        $this->negotiableQuoteManagement->adminSend($negotiableQuoteId);

        $negotiableQuoteRepository = $this->objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $negotiableQuote = $negotiableQuoteRepository->getById($negotiableQuoteId);

        $this->assertEquals(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN, $negotiableQuote->getStatus());
        // confirm snapshot update
        $this->assertEquals(
            NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
            json_decode($negotiableQuote->getSnapshot())->negotiable_quote->status
        );

        // confirm history update
        $historyManagement = $this->objectManager->get(HistoryManagementInterface::class);
        $quoteHistoryItems = $historyManagement->getQuoteHistory($negotiableQuoteId);
        $this->assertCount(1, $quoteHistoryItems);
        /** @var HistoryInterface $quoteHistory */
        $quoteHistory = reset($quoteHistoryItems);
        $this->assertEquals(HistoryInterface::STATUS_UPDATED, $quoteHistory->getStatus());
    }

    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(UserFixture::class, as: 'user'),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$'
                ],
            ],
            'negotiable_quote'
        ),
        DataFixture(ProductFixture::class, as: 'product'),
    ]
    /**
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testAdminSendDraftByAdmin()
    {
        $negotiableQuoteId = DataFixtureStorageManager::getStorage()->get('negotiable_quote')->getId();
        /** @var Product $product */
        $product = DataFixtureStorageManager::getStorage()->get('product');

        // add product to the quote
        $quoteUpdater = $this->objectManager->get(QuoteUpdater::class);
        $quoteUpdater->updateQuote(
            $negotiableQuoteId,
            [
                "addItems" => [
                    [
                        "id" => $product->getId(),
                        "qty" => 1,
                        "sku" => $product->getSku(),
                        "productSku" => $product->getSku(),
                        "config" => null
                    ],
                ],
            ]
        );

        // confirm that email notification was sent
        $this->emailSenderMock->expects($this->once())
            ->method('sendChangeQuoteEmailToBuyer')
            ->with(
                $this->callback(
                    function ($quote) use ($negotiableQuoteId) {
                        return $quote->getId() === $negotiableQuoteId;
                    }
                ),
                Sender::XML_PATH_SELLER_NEW_QUOTE_CREATED_BY_SELLER_TEMPLATE
            );
        $this->negotiableQuoteManagement->adminSend($negotiableQuoteId);

        $negotiableQuoteRepository = $this->objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $negotiableQuote = $negotiableQuoteRepository->getById($negotiableQuoteId);

        $this->assertEquals(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN, $negotiableQuote->getStatus());
        // confirm snapshot update
        $this->assertEquals(
            NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
            json_decode($negotiableQuote->getSnapshot())->negotiable_quote->status
        );

        // confirm history update
        $historyManagement = $this->objectManager->get(HistoryManagementInterface::class);
        $quoteHistoryItems = $historyManagement->getQuoteHistory($negotiableQuoteId);
        $this->assertCount(1, $quoteHistoryItems);
        /** @var HistoryInterface $quoteHistory */
        $quoteHistory = reset($quoteHistoryItems);
        $this->assertEquals(HistoryInterface::STATUS_UPDATED, $quoteHistory->getStatus());
    }

    #[
        AppArea('adminhtml'),
        DataFixture(CustomerFixture::class, as: 'customer'),
        DataFixture(UserFixture::class, as: 'user'),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$'
                ],
            ],
            'negotiable_quote'
        ),
    ]
    /**
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testAdminSendDraftByAdminEmptyQuote()
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An empty B2B quote cannot be sent. You must provide one or more quote items.');
        /** @var NegotiableQuoteInterface $negotiableQuote */
        $negotiableQuote = DataFixtureStorageManager::getStorage()->get('negotiable_quote');
        $this->negotiableQuoteManagement->adminSend($negotiableQuote->getId());
    }
}
