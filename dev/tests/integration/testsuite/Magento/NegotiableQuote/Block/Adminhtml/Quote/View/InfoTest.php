<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Block\Adminhtml\Quote\View;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Test\Fixture\Company as CompanyFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuote as NegotiableQuoteFixture;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Test\Fixture\User as UserFixture;
use PHPUnit\Framework\TestCase;

class InfoTest extends TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var RequestInterface
     */
    private $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->request = $this->objectManager->get(RequestInterface::class);
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
     * @covers \Magento\NegotiableQuote\ViewModel\Quote\Info::getQuoteCreatedBy
     */
    public function testGetQuoteOwnerFullNameWithQuoteByUser()
    {
        $negotiableQuoteId = DataFixtureStorageManager::getStorage()->get('negotiable_quote')->getId();
        $this->request->setParam('quote_id', $negotiableQuoteId);
        $customer = DataFixtureStorageManager::getStorage()->get('customer');

        $block = $this->getBlock();
        $this->assertTrue(is_subclass_of($block, Info::class));
        $this->assertEquals($customer->getName(), (string) $block->getQuoteOwnerFullName());
        $this->assertStringContainsString($customer->getName(), $block->toHtml());
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
            DraftByAdminNegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$'
                ],
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN
            ],
            'negotiable_quote'
        ),
        DataFixture(ProductFixture::class, as: 'product'),
    ]
    /**
     * @covers \Magento\NegotiableQuote\ViewModel\Quote\Info::getQuoteCreatedBy
     */
    public function testGetQuoteOwnerFullNameWithQuoteByMerchant()
    {
        $negotiableQuoteId = DataFixtureStorageManager::getStorage()->get('negotiable_quote')->getId();
        $this->request->setParam('quote_id', $negotiableQuoteId);
        $customer = DataFixtureStorageManager::getStorage()->get('customer');

        $block = $this->getBlock();
        $expected = 'firstname lastname for ' . $customer->getName();
        $this->assertTrue(is_subclass_of($block, Info::class));
        $this->assertStringContainsString($expected, $block->toHtml());
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
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
            ],
            'negotiable_quote'
        ),
    ]
    /**
     * @covers \Magento\NegotiableQuote\ViewModel\Quote\Info::canEdit
     * @param array $statuses
     * @param bool $expectedResult
     * @return void
     * @dataProvider canEditDataProvider
     */
    public function testCanEdit(array $statuses, bool $expectedResult): void
    {
        $repository = $this->objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $quote = DataFixtureStorageManager::getStorage()->get('negotiable_quote');

        /**
         * Some statuses can not be changed unless the quote is in a different status
         * other than NegotiableQuoteInterface::STATUS_CREATED
         */
        foreach ($statuses as $status) {
            $quote->setData(NegotiableQuoteInterface::QUOTE_STATUS, $status);
            $repository->save($quote);
        }

        $this->assertCanEdit(
            (int) $quote->getId(),
            $expectedResult,
            //The expected status is the last status in the array
            end($statuses),
            $quote->getData(NegotiableQuoteInterface::QUOTE_STATUS)
        );
    }

    /**
     * @return array
     */
    public function canEditDataProvider(): array
    {
        return [
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_CREATED],
                'expected' => true
            ],
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_PROCESSING_BY_ADMIN],
                'expected' => true
            ],
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_PROCESSING_BY_CUSTOMER],
                'expected' => false
            ],
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN],
                'expected' => false
            ],
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER],
                'expected' => true
            ],
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_DECLINED],
                'expected' => false
            ],
            [
                'statuses' => [NegotiableQuoteInterface::STATUS_EXPIRED],
                'expected' => false
            ],
            [
                'statuses' => [
                    NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                    NegotiableQuoteInterface::STATUS_ORDERED
                ],
                'expected' => false
            ],
        ];
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
            DraftByAdminNegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ]
            ],
            'negotiable_quote'
        ),
    ]
    /**
     * Test with NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN status
     *
     * @covers \Magento\NegotiableQuote\ViewModel\Quote\Info::canEdit
     */
    public function testCanEditWithDraftQuoteStatus(): void
    {
        $quote = DataFixtureStorageManager::getStorage()->get('negotiable_quote');
        $this->assertCanEdit(
            (int) $quote->getId(),
            true,
            NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN,
            $quote->getData(NegotiableQuoteInterface::QUOTE_STATUS)
        );
    }

    /**
     * @return BlockInterface
     */
    private function getBlock(): BlockInterface
    {
        $page = $this->objectManager->get(PageFactory::class)->create();
        $page->addHandle(['default', 'quotes_quote_view']);
        $page->getLayout()->generateXml();
        return $page->getLayout()->getBlock('negotiable.quote.info');
    }

    /**
     * Assert can edit returns the correct data
     *
     * @param int $id
     * @param bool $expectedResult
     * @param string $expectedStatus
     * @param string $actualStatus
     * @return void
     */
    private function assertCanEdit(int $id, bool $expectedResult, string $expectedStatus, string $actualStatus): void
    {
        $this->assertEquals($expectedStatus, $actualStatus);
        $this->request->setParam('quote_id', $id);
        $block = $this->getBlock();
        $this->assertTrue(is_subclass_of($block, Info::class));
        $this->assertEquals(
            $expectedResult,
            $block->canEdit(),
            'returned unexpected result for status: ' . $expectedStatus
        );
    }
}
