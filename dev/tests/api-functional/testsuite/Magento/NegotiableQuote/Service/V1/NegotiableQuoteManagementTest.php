<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Service\V1;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\AdminTokenServiceInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;

/**
 * Tests for negotiable quote actions (create, send and decline).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppIsolation enabled
 */
class NegotiableQuoteManagementTest extends WebapiAbstract
{
    private const SERVICE_READ_NAME = 'negotiableQuoteNegotiableQuoteManagementV1';

    private const SERVICE_VERSION = 'V1';

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    private $quoteManager;

    /**
     * @var \Magento\Quote\Api\CartItemRepositoryInterface
     */
    private $cartItemRepository;

    /**
     * @var \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface
     */
    private $negotiableRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var int
     */
    private $quoteId;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(
            \Magento\Customer\Api\CustomerRepositoryInterface::class
        );
        $this->quoteManager = $this->objectManager->get(\Magento\Quote\Api\CartManagementInterface::class);
        $this->cartItemRepository = $this->objectManager->get(\Magento\Quote\Api\CartItemRepositoryInterface::class);
        $this->negotiableRepository = $this->objectManager->get(
            \Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface::class
        );
        $this->quoteRepository = $this->objectManager->get(\Magento\Quote\Api\CartRepositoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        try {
            $quote = $this->quoteRepository->get($this->quoteId);
            $this->quoteRepository->delete($quote);
        } catch (\InvalidArgumentException $e) {
            // Do nothing if cart fixture was not used
        }
        parent::tearDown();
    }

    /**
     * Create quote for customer and request negotiable guote.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testRequestQuote()
    {
        $this->quoteId = $this->createQuoteForCustomer();

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/request',
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'create',
            ],
        ];
        $quoteName = 'new quote';
        $result = $this->_webApiCall($serviceInfo, ['quoteId' => $this->quoteId, 'quoteName' => $quoteName]);
        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);

        $this->assertTrue($result, 'Negotiable quote isn\'t created');
        $this->assertEquals($negotiableQuote->getQuoteId(), $this->quoteId, 'Negotiable quote isn\'t created');
        $this->assertEquals($negotiableQuote->getQuoteName(), $quoteName, 'Negotiable quote has incorrect name');
        $this->assertEquals(
            $negotiableQuote->getStatus(),
            NegotiableQuoteInterface::STATUS_CREATED,
            'Negotiable quote has incorrect status'
        );
    }

    /**
     * Create and retrieve quote for customer for test.
     *
     * @return int
     */
    private function createQuoteForCustomer()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $this->quoteId = $this->quoteManager->createEmptyCartForCustomer($customer->getId());
        /** @var \Magento\Quote\Api\Data\CartItemInterface $item */
        $item = $this->objectManager->get(\Magento\Quote\Api\Data\CartItemInterface::class);
        $item->setQuoteId($this->quoteId);
        $item->setSku('simple');
        $item->setQty(1);
        $this->cartItemRepository->save($item);

        return $this->quoteId;
    }

    /**
     * Decline quote for customer.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testDecline()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);
        $negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_PROCESSING_BY_ADMIN);
        $this->negotiableRepository->save($negotiableQuote);
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/decline',
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'decline',
            ],
        ];
        $result = $this->_webApiCall($serviceInfo, ['quoteId' => $this->quoteId, 'reason' => 'decline']);

        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);

        $this->assertTrue($result, 'Negotiable quote isn\'t decline');
        $this->assertEmpty($negotiableQuote->getNegotiatedPriceType(), 'Negotiable quote has incorrect price type');
        $this->assertEmpty($negotiableQuote->getNegotiatedPriceValue(), 'Negotiable quote has incorrect price value');
        $this->assertEquals(
            $negotiableQuote->getStatus(),
            NegotiableQuoteInterface::STATUS_DECLINED,
            'Negotiable quote has incorrect status'
        );
    }

    /**
     * Send quote from merchant to customer.
     *
     * @return void
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoApiDataFixture Magento/NegotiableQuote/_files/negotiable_quote.php
     * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
     */
    public function testSubmitToCustomer()
    {
        $customer = $this->customerRepository->get('email@companyquote.com');
        $quotes = $this->negotiableRepository->getListByCustomerId($customer->getId());
        $this->quoteId = end($quotes)->getId();
        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);
        $priceType = $negotiableQuote->getNegotiatedPriceType();
        $priceValue = $negotiableQuote->getNegotiatedPriceValue();
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/submitToCustomer',
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'adminSend',
            ],
        ];

        $result = $this->_webApiCall($serviceInfo, ['quoteId' => $this->quoteId, 'comment' => 'decline']);

        $negotiableQuote = $this->negotiableRepository->getById($this->quoteId);

        $this->assertTrue($result, 'Negotiable quote isn\'t decline');
        $this->assertEquals(
            $priceType,
            $negotiableQuote->getNegotiatedPriceType(),
            'Negotiable quote has incorrect price type'
        );
        $this->assertEquals(
            $priceValue,
            $negotiableQuote->getNegotiatedPriceValue(),
            'Negotiable quote has incorrect price value'
        );
        $this->assertEquals(
            $negotiableQuote->getStatus(),
            NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
            'Negotiable quote has incorrect status'
        );
    }

    #[
        AppArea('adminhtml'),
        Config('btob/website_configuration/negotiablequote_active', true),
        DataFixture(\Magento\Store\Test\Fixture\Group::class, as: 'group'),
        DataFixture(\Magento\Store\Test\Fixture\Store::class, ['store_group_id' => '$group.id$',], 'store'),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'admin_customer'),
        DataFixture(
            \Magento\User\Test\Fixture\User::class,
            [
                'password' => 'adminPassword123',
                'role_id' => 1,
            ],
            'user'
        ),
        DataFixture(
            \Magento\Company\Test\Fixture\Company::class,
            [
                CompanyInterface::SUPER_USER_ID => '$admin_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'customer'),
        DataFixture(
            \Magento\Company\Test\Fixture\AssignCustomer::class,
            [
                CompanyCustomerInterface::COMPANY_ID => '$company.id$',
                CompanyCustomerInterface::CUSTOMER_ID => '$customer.id$',
            ],
        ),
        DataFixture(
            \Magento\Catalog\Test\Fixture\Product::class,
            [
                'price' => 100,
            ],
            'product'
        ),
    ]
    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSubmitToCustomerDraftQuote()
    {
        $userFixture = DataFixtureStorageManager::getStorage()->get('user');
        $adminTokenService = $this->objectManager->create(AdminTokenServiceInterface::class);
        $adminToken = $adminTokenService
            ->createAdminAccessToken($userFixture->getUsername(), 'adminPassword123');

        // create draft quote
        $quoteDraftServiceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/draft',
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $adminToken,
            ],
            'soap' => [
                'service' => 'negotiableQuoteNegotiableQuoteDraftManagementV1',
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => 'negotiableQuoteNegotiableQuoteDraftManagementV1' . 'CreateDraftByAdmin',
                'token' => $adminToken,
            ],
        ];

        $storeFixture = DataFixtureStorageManager::getStorage()->get('store');
        $customerFixture = DataFixtureStorageManager::getStorage()->get('customer');
        $result = $this->_webApiCall(
            $quoteDraftServiceInfo,
            ['customerId' => $customerFixture->getId()],
            null,
            $storeFixture->getCode(),
        );
        $this->quoteId = $result;

        // add product to the quote
        $quoteUpdateServiceInfo = [
            'rest' => [
                'resourcePath' => str_replace(':quoteId', (string)$this->quoteId, '/V1/negotiableQuote/:quoteId'),
                'httpMethod' => Request::HTTP_METHOD_PUT,
                'token' => $adminToken,
            ],
            'soap' => [
                'service' => 'negotiableQuoteNegotiableCartRepositoryV1',
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => 'negotiableQuoteNegotiableCartRepositoryV1' . 'Save',
                'token' => $adminToken,
            ],
        ];

        $productFixture = DataFixtureStorageManager::getStorage()->get('product');
        $update = [
            'items' => [
                ['sku' => $productFixture->getSku(), 'qty' => 1, 'quoteId' => $this->quoteId]
            ]
        ];

        $quoteData = $this->getQuoteData($adminToken, $storeFixture->getCode());
        $this->_webApiCall(
            $quoteUpdateServiceInfo,
            $this->getQuoteUpdateData($storeFixture, $quoteData['customer'], $update),
            null,
            $storeFixture->getCode(),
        );

        // apply discount
        $quoteData = $this->getQuoteData($adminToken, $storeFixture->getCode());
        $update = [
            'extension_attributes' => [
                'negotiable_quote' => array_merge(
                    $quoteData['extension_attributes']['negotiable_quote'],
                    [
                        'negotiated_price_type' => 1,
                        'negotiated_price_value' => 10,
                    ]
                )
            ]
        ];
        $this->_webApiCall(
            $quoteUpdateServiceInfo,
            $this->getQuoteUpdateData($storeFixture, $quoteData['customer'], $update),
            null,
            $storeFixture->getCode(),
        );

        // update prices
        $updatePricesServiceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/pricesUpdated',
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $adminToken,
            ],
            'soap' => [
                'service' => 'negotiableQuoteNegotiableQuotePriceManagementV1',
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => 'negotiableQuoteNegotiableQuotePriceManagementV1' . 'pricesUpdated',
                'token' => $adminToken,
            ],
        ];
        $this->_webApiCall(
            $updatePricesServiceInfo,
            ['quoteIds' => [$this->quoteId]],
            null,
            $storeFixture->getCode(),
        );

        // submit quote
        $submitToCustomerServiceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/submitToCustomer',
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $adminToken,
            ],
            'soap' => [
                'service' => self::SERVICE_READ_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_READ_NAME . 'adminSend',
                'token' => $adminToken,
            ],
        ];

        // send the quote
        $result = $this->_webApiCall(
            $submitToCustomerServiceInfo,
            ['quoteId' => $this->quoteId, 'comment' => 'Discount 10%'],
            null,
            $storeFixture->getCode(),
        );
        $this->assertTrue($result);

        $quote = $this->quoteRepository->get($this->quoteId);
        $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();

        $this->assertEquals(NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN, $negotiableQuote->getStatus());
        $this->assertEquals(90, $negotiableQuote->getNegotiatedTotalPrice());
        $this->assertEquals($userFixture->getId(), $negotiableQuote->getCreatorId());
        $this->assertEquals(UserContextInterface::USER_TYPE_ADMIN, $negotiableQuote->getCreatorType());
        $this->assertEquals(1, $quote->getItemsQty());
        $this->assertEquals($customerFixture->getId(), $quote->getCustomer()->getId());
    }

    /**
     * Get arguments for quote update API call.
     *
     * @param DataObject $storeFixture
     * @param array $customerData
     * @param array $updateData
     * @return array[]
     */
    public function getQuoteUpdateData(
        DataObject $storeFixture,
        array $customerData,
        array $updateData
    ): array {
        $base = [
            'id' => $this->quoteId,
            'customer' => $customerData,
            'storeId' => $storeFixture->getId(),
        ];

        return ['quote' => array_merge_recursive($base, $updateData)];
    }

    /**
     * Get quote data for the created quote.
     *
     * @param string $token
     * @param string $storeCode
     * @return array
     */
    private function getQuoteData(string $token, string $storeCode)
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/carts/' . $this->quoteId,
                'httpMethod' => Request::HTTP_METHOD_GET,
                'token' => $token,
            ],
            'soap' => [
                'service' => 'quoteCartRepositoryV1',
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => 'quoteCartRepositoryV1' . 'Get',
                'token' => $token,
            ],
        ];
        $cartData = $this->_webApiCall($serviceInfo, ['cartId' => $this->quoteId], null, $storeCode);
        unset($cartData['extension_attributes']['negotiable_quote']['original_total_price']);
        unset($cartData['extension_attributes']['negotiable_quote']['base_original_total_price']);
        unset($cartData['extension_attributes']['negotiable_quote']['negotiated_total_price']);
        unset($cartData['extension_attributes']['negotiable_quote']['base_negotiated_total_price']);

        return $cartData;
    }
}
