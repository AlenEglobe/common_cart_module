<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Block\Quote;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test class for negotiable quote items block
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ItemsTest extends TestCase
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableQuoteRepository;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @var Items
     */
    private $itemsBlock;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->itemsBlock = $objectManager->create(Items::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->session = $objectManager->get(Session::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->negotiableQuoteRepository = $objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->layout = $objectManager->get(LayoutInterface::class);
        $this->pageFactory = $objectManager->get(PageFactory::class);

        parent::setUp();
    }

    /**
     * Test gift card product options visible in negotiable quote view
     *
     * @param string $layout
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_giftcard_products.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     *
     * @dataProvider layoutDataProvider
     */
    public function testGiftCardProductOptionsInView($layout): void
    {
        // Login as company admin
        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        // Get subordinate's negotiable quote
        $negotiableQuote = $this->getNegotiableQuoteForCustomer('veronica.costello@example.com');
        $negotiableQuoteId = $negotiableQuote->getEntityId();

        $this->itemsBlock->getRequest()->setParam('quote_id', $negotiableQuoteId);
        $block = $this->loadBlock($layout);
        $itemsHtml = $block->toHtml();

        $giftCardOptionsToAssert = [
            'giftcard_amount' => '1',
            'giftcard_sender_name' => 'test sender name',
            'giftcard_recipient_name' => 'test recipient name',
            'giftcard_sender_email' => 'sender@example.com',
            'giftcard_recipient_email' => 'recipient@example.com',
            'giftcard_message' => 'message text',
        ];

        // Assert that gift card options is visible in negotiable quote view
        foreach ($giftCardOptionsToAssert as $giftCardOptionToAssert) {
            $this->assertStringContainsString($giftCardOptionToAssert, $itemsHtml);
        }
    }

    /**
     * Test bundle product options visible in negotiable quote view
     *
     * @param string $layout
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_bundle_products.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @dataProvider layoutDataProvider
     */
    public function testBundleProductOptionsInView($layout): void
    {
        // Login as company admin
        $companyAdmin = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($companyAdmin->getId());

        // Get subordinate's negotiable quote
        $negotiableQuote = $this->getNegotiableQuoteForCustomer('veronica.costello@example.com');
        $negotiableQuoteId = $negotiableQuote->getEntityId();

        $this->itemsBlock->getRequest()->setParam('quote_id', $negotiableQuoteId);
        $block = $this->loadBlock($layout);
        $itemsHtml = $block->toHtml();

        $bundleProductOptionsToAssert = [
            'Option 1',
            'Option 2',
            'Option 3',
            'Option 4',
            'Option 5',
            '1 x Simple Product1',
            '1 x Simple Product2'
        ];

        // Assert that bundle product options is visible in negotiable quote view
        foreach ($bundleProductOptionsToAssert as $bundleProductOptionToAssert) {
            $this->assertStringContainsString($bundleProductOptionToAssert, $itemsHtml);
        }
    }

    /**
     * @param string $customerEmail
     * @return \Magento\Framework\Api\ExtensibleDataInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getNegotiableQuoteForCustomer(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(NegotiableQuoteInterface::CREATOR_ID, $customer->getId())
            ->create();
        $negotiableQuotes = $this->negotiableQuoteRepository->getList($searchCriteria)->getItems();
        return array_shift($negotiableQuotes);
    }

    /**
     * Load items block
     *
     * @param string $layout
     * @return Items
     */
    private function loadBlock($layout): Items
    {
        $page = $this->pageFactory->create();
        $page->addHandle([
            'default',
            $layout,
        ]);
        $page->getLayout()->generateXml();
        $quoteViewBlock = $page->getLayout()->getBlock('quote.view');
        return $quoteViewBlock->getChildBlock('quote_items');
    }

    /**
     * Data provider for layouts to check
     *
     * @return array|\string[][]
     */
    public function layoutDataProvider() : array
    {
        return [
            'view layout' => ['negotiable_quote_quote_view'],
            'print layout' => ['negotiable_quote_quote_print']
        ];
    }
}
