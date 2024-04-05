<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Model\History;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Test\Fixture\Company as CompanyFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Helper\Quote;
use Magento\NegotiableQuote\Model\CommentManagementInterface;
use Magento\NegotiableQuote\Model\Quote\History;
use Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuote;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\User\Test\Fixture\User as UserFixture;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LogInformationTest extends TestCase
{
    /**
     * @var LogInformation
     */
    private $logInformation;
    /**
     * @var ObjectManager
     */
    private $objectManager;
    /**
     * @var RequestInterface&MockObject
     */
    private $requestMock;
    /**
     * @var History
     */
    private $quoteHistory;
    /**
     * @var CartRepositoryInterface|mixed
     */
    private $quoteRepository;

    public function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->getMockForAbstractClass();
        $context = $this->objectManager->create(
            Context::class,
            [
                'httpRequest' => $this->requestMock
            ]
        );
        $negotiableQuoteHelper = $this->objectManager->create(Quote::class, ['context' => $context]);
        $this->quoteHistory = $this->objectManager->get(History::class);
        $this->logInformation = $this->objectManager->create(
            LogInformation::class,
            [
                'negotiableQuoteHelper' => $negotiableQuoteHelper
            ]
        );
    }

    /**
     * @param array $quoteChangeData
     * @param array $commentData
     * @param array $historyData
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @dataProvider getQuoteHistoryForDraftQuoteDataProvider
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(CustomerFixture::class, as: 'admin_customer'),
        DataFixture(UserFixture::class, as: 'user'),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$admin_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            DraftByAdminNegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #10',
                'quote' => [
                    'customer_id' => '$admin_customer.id$',
                    ]
            ],
            'quote'
        ),
        DataFixture(ProductFixture::class, ['price' => 100], 'product'),
    ]
    public function testGetQuoteHistoryForDraftQuote(
        array $quoteChangeData,
        array $commentData,
        array $historyData
    ) {
        $comment = $commentData['message'] ?? '';

        /** @var NegotiableQuoteInterface $quote */
        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $quoteId = (int)$quote->getQuoteId();
        $this->requestMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('quote_id')
            ->willReturn($quoteId);

        $this->quoteHistory->createLog($quoteId);

        // add product to the quote
        $negotiableQuoteManagement = $this->objectManager->get(NegotiableQuoteManagementInterface::class);
        /** @var Product $product */
        $product = DataFixtureStorageManager::getStorage()->get('product');
        $negotiableQuoteManagement->saveAsDraft(
            $quoteId,
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

        // update quote data
        $negotiableQuoteManagement->saveAsDraft(
            $quoteId,
            $quoteChangeData,
            $commentData
        );

        $negotiableQuoteManagement->adminSend($quoteId, $comment);

        $this->validateHistoryData($historyData, $quoteId);
    }

    /**
     * @return array[]
     */
    public function getQuoteHistoryForDraftQuoteDataProvider(): array
    {
        return [
            'Percentage discount, comment and changed quote name' => [
                'quoteChangeData' => [
                    "name" => 'Quote Name New',
                    "proposed" => [
                        "type" => NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                        "value" => '30'
                    ],
                ],
                'commentData' => [
                    'message' => 'The best price just for you',
                ],
                'historyData' => [
                    [
                        'status' => [
                            'new_value' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
                        ],
                    ],
                    [
                        'status' => [
                            'old_value' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
                            'new_value' => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                        ],
                        'quote_name' => [
                            'old_value' => 'Quote #10',
                            'new_value' => 'Quote Name New',
                        ],
                        'subtotal' => [
                            'new_value' => 70,
                        ],
                        'comment' => 'The best price just for you',
                    ]
                ],
            ],
            'Amount discount and changed expiration date' => [
                'quoteChangeData' => [
                    "proposed" => [
                        "type" => NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_AMOUNT_DISCOUNT,
                        "value" => '5'
                    ],
                    'expiration_period' => (new \DateTime('+10 days'))->format('Y-m-d'),
                ],
                'commentData' => [],
                'historyData' => [
                    [
                        'status' => [
                            'new_value' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
                        ],
                    ],
                    [
                        'status' => [
                            'old_value' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
                            'new_value' => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                        ],
                        'subtotal' => [
                            'new_value' => 95,
                        ],
                        'expiration_date' => [
                            'new_value' => (new \DateTime('+10 days'))->format('Y-m-d'),
                        ],
                    ]
                ],
            ],
            'Proposed total' => [
                'quoteChangeData' => [
                    "proposed" => [
                        "type" => NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PROPOSED_TOTAL,
                        "value" => '50'
                    ],
                ],
                'commentData' => [],
                'historyData' => [
                    [
                        'status' => [
                            'new_value' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
                        ],
                    ],
                    [
                        'status' => [
                            'old_value' => NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
                            'new_value' => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                        ],
                        'subtotal' => [
                            'new_value' => 50,
                        ],
                    ]
                ],
            ],
        ];
    }

    /**
     * @param array $quoteChangeData
     * @param array $commentData
     * @param array $historyData
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @dataProvider getQuoteHistoryForCustomerCreatedQuoteDataProvider
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(CustomerFixture::class, as: 'admin_customer'),
        DataFixture(UserFixture::class, as: 'user'),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$admin_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(ProductFixture::class, ['price' => 100], 'product'),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$admin_customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 3,
                        ],
                        ],
                    ],
            ],
            'quote'
        ),
    ]
    public function testGetQuoteHistoryForCustomerCreatedQuote(
        array $quoteChangeData,
        array $commentData,
        array $historyData
    ) {
        $customerComment = 'Can I get a discount?';
        $comment = $commentData['message'] ?? '';

        /** @var NegotiableQuoteInterface $quote */
        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $quoteId = (int)$quote->getQuoteId();
        $this->requestMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('quote_id')
            ->willReturn($quoteId);

        // add customer comment and create log entry
        $commentManagement = $this->objectManager->get(CommentManagementInterface::class);
        $commentManagement->update($quoteId, $customerComment);
        $this->quoteHistory->createLog($quoteId);

        $negotiableQuoteManagement = $this->objectManager->get(NegotiableQuoteManagementInterface::class);
        $negotiableQuoteManagement->openByMerchant($quoteId);
        $negotiableQuoteManagement->saveAsDraft(
            $quoteId,
            $quoteChangeData,
            $commentData
        );

        $negotiableQuoteManagement->adminSend($quoteId, $comment);

        $this->validateHistoryData($historyData, $quoteId);
    }

    /**
     * @return array[]
     */
    public function getQuoteHistoryForCustomerCreatedQuoteDataProvider(): array
    {
        return [
            'Percentage discount, comment and changed quote name' => [
                'quoteChangeData' => [
                    "name" => 'Quote #11 Name New',
                    "proposed" => [
                        "type" => NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                        "value" => '30'
                    ],
                ],
                'commentData' => [
                    'message' => 'Discount 30%',
                ],
                'historyData' => [
                    [
                        'status' => [
                            'new_value' => NegotiableQuoteInterface::STATUS_CREATED,
                        ],
                        'comment' => 'Can I get a discount?',
                    ],
                    [
                        'status' => [
                            'old_value' => NegotiableQuoteInterface::STATUS_CREATED,
                            'new_value' => NegotiableQuoteInterface::STATUS_PROCESSING_BY_ADMIN,
                        ],
                    ],
                    [
                        'status' => [
                            'old_value' =>NegotiableQuoteInterface::STATUS_PROCESSING_BY_ADMIN,
                            'new_value' => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                        ],
                        'quote_name' => [
                            'old_value' => 'Quote #11',
                            'new_value' => 'Quote #11 Name New',
                        ],
                        'subtotal' => [
                            'new_value' => 210,
                        ],
                        'comment' => 'Discount 30%',
                    ]
                ],
            ],
        ];
    }

    /**
     * @param array $historyData
     * @return void
     */
    private function validateHistoryData(array $historyData, int $quoteId): void
    {
        $history = $this->logInformation->getQuoteHistory();
        $this->assertCount(count($historyData), $history);

        $logCommentsInformation = $this->objectManager->get(LogCommentsInformation::class);
        $index = 0;
        foreach ($history as $historyLog) {
            $expectedLogData = $historyData[$index];
            $update = $this->logInformation->getQuoteUpdates($historyLog->getHistoryId());

            // validate expiration date if it is set
            if (!isset($expectedLogData['expiration_date']) && $update->hasData('expiration_date')) {
                $quote = $this->quoteRepository->get($quoteId);
                $this->assertEquals(
                    ['new_value' => $quote->getExtensionAttributes()->getNegotiableQuote()->getExpirationPeriod()],
                    $update->getData('expiration_date')
                );
                $update->unsetData('expiration_date');
            }

            foreach ($expectedLogData as $param => $data) {
                $value = $update->getData($param);
                if ($param === 'comment') {
                    $value = $logCommentsInformation->getCommentText($value);
                }
                $this->assertEquals($data, $value, 'No "' . $param . '" value');
            }

            $excessiveData = array_diff_key(array_filter($update->getData()), $expectedLogData);
            $this->assertEmpty($excessiveData, 'Excessive history data: ' . json_encode($excessiveData));

            $index++;
        }
    }
}
