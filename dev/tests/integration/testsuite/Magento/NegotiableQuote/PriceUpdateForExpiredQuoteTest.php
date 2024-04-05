<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Cron\ExpireQuote;
use Magento\NegotiableQuote\Model\Quote\Address as NegotiableQuoteAddress;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as NegotiableQuoteResourceModel;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Rule as SalesRule;
use Magento\SharedCatalog\Api\PriceManagementInterface as SharedCatalogPriceManagement;
use Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\Collection as SharedCatalogCollection;
use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Util\Test as TestUtil;

/**
 * Test price updates for expired Negotiable Quote
 *
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PriceUpdateForExpiredQuoteTest extends AbstractBackendController
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $negotiableQuoteRepository;

    /**
     * @var NegotiableQuoteResourceModel
     */
    private $negotiableQuoteResourceModel;

    /**
     * @var SharedCatalogPriceManagement
     */
    private $sharedCatalogPriceManagement;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ExpireQuote
     */
    private $expireQuote;

    /**
     * @var NegotiableQuoteManagementInterface
     */
    private $negotiableQuoteManagement;

    /**
     * @var SharedCatalogCollection
     */
    private $sharedCatalogCollection;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->customerSession = $this->_objectManager->get(CustomerSession::class);
        $this->customerRepository = $this->_objectManager->create(CustomerRepositoryInterface::class);
        $this->negotiableQuoteRepository = $this->_objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $this->negotiableQuoteResourceModel = $this->_objectManager->get(NegotiableQuoteResourceModel::class);
        $this->sharedCatalogPriceManagement = $this->_objectManager->get(SharedCatalogPriceManagement::class);
        $this->productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);
        $this->expireQuote = $this->_objectManager->get(ExpireQuote::class);
        $this->negotiableQuoteManagement = $this->_objectManager->get(NegotiableQuoteManagementInterface::class);
        $this->sharedCatalogCollection = $this->_objectManager->get(SharedCatalogCollection::class);
    }

    /**
     * Given a negotiable quote created by a company customer in a shared catalog
     *
     * When the negotiable quote is set to expired status
     * And a custom price is set for the product in the negotiable quote
     * And a cart price rule is added for the customer group belonging to the shared catalog
     * And the company customer visits the negotiable quote on the storefront
     * Then the negotiable quote has a status of Expired
     * And a message about the quote being expired and the catalog prices being updated is visible
     * And the prices are still updated in the history log
     * And the Proceed to Checkout button is enabled
     * And visiting the URL for the Proceed to Checkout button returns a 200 response code
     *
     * When the custom price is changed for the product in the negotiable quote
     * And the cart price rule is changed for the customer group belonging to the shared catalog
     * And the company customer visits the negotiable quote on the storefront
     * Then the negotiable quote has a status of Expired
     * And a message about the quote being expired and the catalog prices being updated is visible
     * And the prices are still updated in the history log
     *
     * When the negotiable quote shipping address is updated
     * And the company customer visits the negotiable quote on the storefront
     * Then the negotiable quote has a status of Open
     *
     * When the custom price is changed again for the product in the negotiable quote
     * And the cart price rule is changed again for the customer group belonging to the shared catalog
     * And the company customer visits the negotiable quote on the storefront
     * Then the customer sees a message about the catalog prices being updated
     *
     * @magentoAppArea adminhtml
     *
     * @magentoDataFixture Magento/NegotiableQuote/_files/nq_for_company_customer_with_product_in_shared_catalog.php
     * @magentoDataFixture Magento/Customer/_files/customer_address.php
     *
     * @magentoConfigFixture base_website btob/website_configuration/sharedcatalog_active true
     * @magentoConfigFixture base_website btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     * @magentoConfigFixture base_website btob/website_configuration/company_active true
     * @magentoConfigFixture current_store catalog/price/scope 1
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExpiredNegotiableQuotePricesStillUpdate(): void
    {
        $quote = $this->getQuoteCreatedInFixture();

        $product = $this->productRepository->get('simple');

        /** @var SharedCatalog $sharedCatalog */
        $sharedCatalog = $this->sharedCatalogCollection->getLastItem();

        // send quote to admin (merchant) (STATUS_SUBMITTED_BY_CUSTOMER)
        $this->assertTrue($this->negotiableQuoteManagement->send($quote->getId()));

        // admin opens it (STATUS_PROCESSING_BY_ADMIN)
        $this->assertTrue($this->negotiableQuoteManagement->openByMerchant($quote->getId()));

        // admin sends it back to customer (STATUS_SUBMITTED_BY_ADMIN)
        $this->assertTrue($this->negotiableQuoteManagement->adminSend($quote->getId()));

        // Set negotiable quote expiration date to way back in the past
        $this->negotiableQuoteResourceModel->getConnection()->update(
            $this->negotiableQuoteResourceModel->getMainTable(),
            ['expiration_period' => '1971-01-01'],
            ['quote_id' => $quote->getId()]
        );

        // run ExpireQuote cron job
        $this->expireQuote->execute();

        // Assert that quote's status is now expired
        $this->assertQuoteStatusInDatabase($this->getQuoteCreatedInFixture(), NegotiableQuoteInterface::STATUS_EXPIRED);

        // set custom price of product in shared catalog to $7.00
        $this->setCustomFixedPriceToProductInSharedCatalog($product, $sharedCatalog, 7);

        // create sales rule in shared catalog with 30% discount
        $salesRule = $this->createPercentageBasedCartPriceRuleInSharedCatalog($sharedCatalog, 30);

        $customer = $this->customerRepository->get('email@companyquote.com');
        $this->customerSession->loginById($customer->getId());

        $this->getQuoteRecalculationAjaxResponse($quote);

        $response = $this->getQuotePageResponse($quote);

        $this->assertQuoteStatusInResponseBody($response->getBody(), 'Expired');

        $this->assertExpiredQuotePriceUpdateNoticeInResponseBody($response->getBody());

        // verify the history log details
        $latestLogEntry = $this->getLatestLogEntryFromResponseBody($response->getBody());
        $this->assertEquals(
            [
                [
                    'title' => $product->getName(),
                    'info' => [
                        "Cart Price: $50.00 $4.90",
                        "Catalog Price: $50.00 $7.00"
                    ],
                ],
                [
                    'title' => 'Quote Discount',
                    'info' => [
                        'Discount amount: $2.10'
                    ]
                ],
            ],
            $latestLogEntry
        );

        // Verify that 'Proceed to Checkout' button is enabled
        $this->assertProceedToCheckoutButtonIsEnabledInResponseBody($response->getBody());

        // now, change sales (cart) rule amount and custom product price

        // update existing sales (cart) rule discount amount to 10%
        $salesRule->setDiscountAmount(10)->save();

        // change custom price of product in shared catalog to $6.00
        $this->setCustomFixedPriceToProductInSharedCatalog($product, $sharedCatalog, 6);

        $this->getQuoteRecalculationAjaxResponse($quote);

        $response = $this->getQuotePageResponse($quote);

        $this->assertQuoteStatusInResponseBody($response->getBody(), 'Expired');
        $this->assertExpiredQuotePriceUpdateNoticeInResponseBody($response->getBody());

        // verify the history log details
        $latestLogEntry = $this->getLatestLogEntryFromResponseBody($response->getBody());

        $this->assertEquals(
            [
                [
                    'title' => $product->getName(),
                    'info' => [
                        "Cart Price: $4.90 $5.40",
                        "Catalog Price: $7.00 $6.00"
                    ],
                ],
                [
                    'title' => 'Quote Discount',
                    'info' => [
                        'Discount amount: $2.10 $0.60'
                    ]
                ],
            ],
            $latestLogEntry
        );

        $this->getQuoteRecalculationAjaxResponse($quote);

        // now, assert negotiable quote's status is set to "Open" after updating quote shipping address
        $customerAddresses = $customer->getAddresses();
        $customerAddress = end($customerAddresses);

        // create NegotiableQuoteAddress with frontend area dependencies
        /** @var NegotiableQuoteAddress $negotiableQuoteAddress */
        $negotiableQuoteAddress = $this->_objectManager->get(NegotiableQuoteAddress::class);
        $negotiableQuoteAddress->updateQuoteShippingAddress($quote->getId(), $customerAddress);

        $response = $this->getQuotePageResponse($quote);

        $this->assertQuoteStatusInResponseBody($response->getBody(), 'Open');

        // update existing sales (cart) rule discount amount back to 30%
        $salesRule->setDiscountAmount(30)->save();

        // change custom price of product in shared catalog back to $7.00
        $this->setCustomFixedPriceToProductInSharedCatalog($product, $sharedCatalog, 7);

        // assert "catalog prices have changed" message appears when recalculating an open negotiable quote
        $response = $this->getQuoteRecalculationAjaxResponse($quote);
        $this->assertOpenQuotePriceUpdateNoticeInResponseBody($response->getBody());
    }

    /**
     * Set custom fixed price to the product in the shared catalog
     *
     * @param ProductInterface $product
     * @param SharedCatalog $sharedCatalog
     * @param int $customPrice
     */
    private function setCustomFixedPriceToProductInSharedCatalog(
        ProductInterface $product,
        SharedCatalog $sharedCatalog,
        int $customPrice
    ) {
        // update shared catalog product tier price
        $tierPrices[$product->getId()] = [
            [
                'qty' => 1,
                'customer_group_id' => $sharedCatalog->getCustomerGroupId(),
                'value' => $customPrice,
                'website_id' => 1,
            ],
        ];

        $this->sharedCatalogPriceManagement->saveProductTierPrices($sharedCatalog, $tierPrices);
    }

    /**
     * Create Percentage-Based Cart Price (Sales) Rule in $sharedCatalog
     *
     * @param SharedCatalog $sharedCatalog
     * @param int $percentage
     * @return SalesRule
     * @throws \Exception
     */
    private function createPercentageBasedCartPriceRuleInSharedCatalog(SharedCatalog $sharedCatalog, int $percentage)
    {
        /** @var SalesRule $salesRule */
        $salesRule = $this->_objectManager->create(SalesRule::class);
        $salesRule->setData(
            [
                'name' => "$percentage% Off of order",
                'is_active' => 1,
                'customer_group_ids' => [$sharedCatalog->getCustomerGroupId()],
                'coupon_type' => SalesRule::COUPON_TYPE_NO_COUPON,
                'simple_action' => 'by_percent',
                'discount_amount' => $percentage,
                'discount_step' => 0,
                'stop_rules_processing' => 1,
                'website_ids' => [1],
                'conditions' => [],
            ]
        );

        $salesRule->save();

        return $salesRule;
    }

    /**
     * @param Quote $quote
     * @return ResponseInterface
     * @throws LocalizedException
     */
    private function getQuotePageResponse(Quote $quote): ResponseInterface
    {
        $this->reinitializeAppInDesiredArea('frontend');

        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('negotiable_quote/quote/view/quote_id/' . $quote->getId() . '?refresh=' . rand(1, 100));
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        return $this->getResponse();
    }

    /**
     * Get AJAX response that isdispatched from the storefront client via JS upon viewing the quote
     *
     * @param Quote $quote
     * @return ResponseInterface
     * @throws LocalizedException
     */
    private function getQuoteRecalculationAjaxResponse(Quote $quote): ResponseInterface
    {
        $this->reinitializeAppInDesiredArea('frontend');

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setParam('isAjax', true);
        $this->dispatch('negotiable_quote/quote/recalculate/quote_id/' . $quote->getId());
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());

        return $this->getResponse();
    }

    /**
     * Assert Quote Status in database is that of $expectedStatus
     *
     * @param Quote $quote
     * @param string $expectedStatus
     */
    private function assertQuoteStatusInDatabase(Quote $quote, string $expectedStatus): void
    {
        $actualStatus = $quote->getExtensionAttributes()->getNegotiableQuote()->getStatus();
        $this->assertEquals(
            $expectedStatus,
            $actualStatus,
            'Quote status is not ' . $expectedStatus . ' in database'
        );
    }

    /**
     * Assert Quote Status in $responseBody is that of $expectedStatus
     *
     * @param string $responseBody
     * @param string $expectedStatus
     */
    private function assertQuoteStatusInResponseBody(string $responseBody, string $expectedStatus): void
    {
        $this->assertStringContainsString(
            '<span id="quote-status-field" class="quote-status">' . $expectedStatus . '</span>',
            $responseBody,
            'Quote status is not ' . $expectedStatus . ' in response body'
        );
    }

    /**
     * Assert Expired Quote Price Update Notice is present in $responseBody
     * @param string $responseBody
     */
    private function assertExpiredQuotePriceUpdateNoticeInResponseBody(string $responseBody)
    {
        $this->assertStringContainsString(
            'Your quote has expired and the product prices have been updated as per the latest prices in ' .
            'your catalog. You can either re-submit the quote to seller for further negotiation or go to checkout.',
            $responseBody,
            'Expired quote notice is unexpectedly not displayed'
        );
    }

    /**
     * Assert Open Quote Price Update Notice is present in $responseBody
     * @param string $responseBody
     */
    private function assertOpenQuotePriceUpdateNoticeInResponseBody(string $responseBody)
    {
        $this->assertStringContainsString(
            'Catalog prices have changed. You may want to re-submit this quote. ' .
            'Details about the changes are available in the History Log.',
            $responseBody,
            'Open quote catalog price change notice is unexpectedly not in response after updating '
        );
    }

    /**
     * Get Quote created in fixture
     *
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getQuoteCreatedInFixture(): Quote
    {
        $customer = $this->customerRepository->get('email@companyquote.com');

        /** @var Quote[] $quotes */
        $quotes = $this->negotiableQuoteRepository->getListByCustomerId($customer->getId());

        return end($quotes);
    }

    /**
     * Reinitialize application in $desiredAppArea
     *
     * @param string $desiredAppArea
     * @throws LocalizedException
     */
    private function reinitializeAppInDesiredArea(string $desiredAppArea)
    {
        $app = Bootstrap::getInstance()->getBootstrap()->getApplication();

        $app->reinitialize();

        $app->loadArea($desiredAppArea);

        $mutableScopeConfig = $this->_objectManager->get(MutableScopeConfigInterface::class);
        $annotations = TestUtil::parseTestMethodAnnotations(
            get_class($this),
            $this->getName(false)
        );

        // re-enable config fixtures that have been removed due to re-initialization
        $magentoConfigFixtureAnnotations = $annotations['method']['magentoConfigFixture'];

        $configValuesByPath = [];

        foreach ($magentoConfigFixtureAnnotations as $magentoConfigFixtureAnnotation) {
            list(, $path, $value) = explode(' ', $magentoConfigFixtureAnnotation);
            $configValuesByPath[$path] = $value;
        }

        foreach ($configValuesByPath as $path => $value) {
            $mutableScopeConfig->setValue(
                $path,
                $value,
                ScopeInterface::SCOPE_WEBSITES,
                'base'
            );

            $mutableScopeConfig->setValue(
                $path,
                $value,
                ScopeInterface::SCOPE_STORE
            );
        }

        $this->resetRequest();
        $this->resetResponse();
    }

    /**
     * Reset response singleton
     */
    private function resetResponse()
    {
        Bootstrap::getObjectManager()->removeSharedInstance(ResponseInterface::class);
        $this->_response = null;
    }

    /**
     * Get latest history log entry from negotiable quote page's response body
     *
     * @param string $responseBody
     * @return array
     */
    private function getLatestLogEntryFromResponseBody(string $responseBody)
    {
        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $domDocument->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($responseBody);
        libxml_use_internal_errors(false);

        $xpathFinder = new \DOMXPath($domDocument);

        $logNodes = $xpathFinder->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " history-log-block-item ")]'
        );

        /** @var \DOMNode $lastLogNode */
        $lastLogNode = $logNodes->item($logNodes->count() - 1);

        $logDescriptions = [];

        $descriptionNodes = $xpathFinder->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " history-log-block-item-action-describe ")]',
            $lastLogNode
        );

        /** @var \DOMNode $descriptionNode */
        foreach ($descriptionNodes as $descriptionNode) {
            $logDescription = [];

            $titleNode = $xpathFinder->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " history-log-block-item-title ")]',
                $descriptionNode
            )->item(0);

            $logDescription['title'] = trim(preg_replace('#\s+#', ' ', $titleNode->textContent));

            $infoNodes = $xpathFinder->query(
                './/*[contains(concat(" ", normalize-space(@class), " "), " history-log-block-item-info ")]',
                $descriptionNode
            );

            $logDescription['info'] = [];

            foreach ($infoNodes as $infoNode) {
                $logDescription['info'][] = trim(preg_replace('#\s+#', ' ', $infoNode->textContent));
            }

            sort($logDescription['info']);

            $logDescriptions[] = $logDescription;
        }

        return $logDescriptions;
    }

    /**
     * Assert Proceed to Checkout button in $responseBody is enabled
     *
     * @param string $responseBody
     */
    private function assertProceedToCheckoutButtonIsEnabledInResponseBody(string $responseBody): void
    {
        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $domDocument->preserveWhiteSpace = false;
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($responseBody);
        libxml_use_internal_errors(false);

        $xpathFinder = new \DOMXPath($domDocument);

        /** @var \DOMElement $actionButtonContainerNode */
        $actionButtonContainerNode = $xpathFinder->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " quote-view-buttons ")]'
        )->item(0);

        /** @var \DOMElement $checkoutButtonNode */
        $checkoutButtonNode = $xpathFinder->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " checkout ")]',
            $actionButtonContainerNode
        )->item(0);

        $checkoutButtonClass = $checkoutButtonNode->getAttribute('class');

        $this->assertStringContainsString('action', $checkoutButtonClass);
        $this->assertStringContainsString('checkout', $checkoutButtonClass);

        // Assert that Proceed to Checkout action is enabled
        $this->assertStringNotContainsString(
            'disabled',
            $checkoutButtonClass
        );

        // Assert that visiting the button's URL returns 200 response code
        $checkoutButtonUrl = json_decode($checkoutButtonNode->getAttribute('data-post'), true)['action'];

        $this->assertNotEmpty($checkoutButtonUrl);

        $this->dispatch($checkoutButtonUrl);
        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
    }
}
