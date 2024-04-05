<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Block\Quote;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Test\Fixture\Company as CompanyFixture;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuote as NegotiableQuoteFixture;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\User\Test\Fixture\User as UserFixture;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\Customer\Model\Session;
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

    /**
     * @var Session
     */
    private $customerSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->request = $this->objectManager->get(RequestInterface::class);
        $this->customerSession = $this->objectManager->get(Session::class);
    }

    #[
        AppArea('frontend'),
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
    public function testGetQuoteCreatedByWithQuoteByUser()
    {
        $negotiableQuoteId = DataFixtureStorageManager::getStorage()->get('negotiable_quote')->getId();
        $this->request->setParam('quote_id', $negotiableQuoteId);
        $customer = DataFixtureStorageManager::getStorage()->get('customer');
        $this->customerSession->loginById($customer->getId());

        $block = $this->getBlock();
        $this->assertTrue(is_subclass_of($block, Info::class));
        $this->assertStringContainsString($customer->getName(), $block->toHtml());
    }

    #[
        AppArea('frontend'),
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
    public function testGetQuoteCreatedByWithQuoteByMerchant()
    {
        $negotiableQuoteId = DataFixtureStorageManager::getStorage()->get('negotiable_quote')->getId();
        $this->request->setParam('quote_id', $negotiableQuoteId);
        $customer = DataFixtureStorageManager::getStorage()->get('customer');
        $this->customerSession->loginById($customer->getId());

        $block = $this->getBlock();
        $expected = 'firstname lastname for ' . $customer->getName();
        $this->assertTrue(is_subclass_of($block, Info::class));
        $this->assertStringContainsString($expected, $block->toHtml());
    }

    /**
     * @return BlockInterface
     */
    private function getBlock(): BlockInterface
    {
        $page = $this->objectManager->get(PageFactory::class)->create();
        $page->addHandle(['default', 'negotiable_quote_quote_view']);
        $page->getLayout()->generateXml();
        return $page->getLayout()->getBlock('quote.date');
    }
}
