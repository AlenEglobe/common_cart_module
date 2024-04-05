<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Api;

use Magento\Catalog\Test\Fixture\Product;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteRepositoryFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Framework\Webapi\Rest\Request;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\User\Test\Fixture\User;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuote;

use Magento\NegotiableQuote\Test\Fixture\ApplyQuoteConfigForCompany;

use Magento\TestFramework\Helper\Bootstrap;

class LineItemDiscountTest extends WebapiAbstract
{
    private const RESOURCE_PATH = '/V1/carts/%d/items/%d';

    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    private $objectManager;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var QuoteRepositoryFactory
     */
    private $quoteRepositoryFactory;

    protected function setUp(): void
    {
        $this->_markTestAsRestOnly();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->quoteRepositoryFactory = $this->objectManager->get(QuoteRepositoryFactory::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ],
                    ],
                ],
            ],
            'negotiable_quote'
        )
    ]
    public function testAddItemPercentDiscount(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');

        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $resourcePath = sprintf(self::RESOURCE_PATH, $quote->getId(), $quoteItem->getId());
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ]
        ];
        $requestData = [
            'cartItem' => [
                'item_id' => $quoteItem->getId(),
                'quote_id' => $quote->getId(),
                'qty' => 3,
                'extension_attributes' => [
                    'negotiable_quote_item' => [
                        'item_id' => $quoteItem->getId(),
                        'extension_attributes' => [
                            'negotiated_price_type' => 1,
                            'negotiated_price_value' => 10
                        ]
                    ]
                ]
            ]
        ];

        $itemReturned = $this->_webApiCall($serviceInfo, $requestData);
        $quoteRepository = $this->quoteRepositoryFactory->create();
        $actualQuote = $quoteRepository->get($quote->getId());
        $dbItem = $actualQuote->getItemById($quoteItem->getId());
        $this->assertEquals(9.0, $itemReturned['price']);
        $this->assertEquals(27.0, $dbItem->getRowTotal());
        $this->assertEquals(27.0, $actualQuote->getSubtotal());
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ],
                    ],
                ],
            ],
            'negotiable_quote'
        )
    ]
    public function testAddItemFixedDiscount(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');

        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $resourcePath = sprintf(self::RESOURCE_PATH, $quote->getId(), $quoteItem->getId());
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ]
        ];
        $requestData = [
            'cartItem' => [
                'item_id' => $quoteItem->getId(),
                'quote_id' => $quote->getId(),
                'qty' => 3,
                'extension_attributes' => [
                    'negotiable_quote_item' => [
                        'item_id' => $quoteItem->getId(),
                        'extension_attributes' => [
                            'negotiated_price_type' => 2,
                            'negotiated_price_value' => 4.55
                        ]
                    ]
                ]
            ]
        ];

        $itemReturned = $this->_webApiCall($serviceInfo, $requestData);
        $quoteRepository = $this->quoteRepositoryFactory->create();
        $actualQuote = $quoteRepository->get($quote->getId());
        $dbItem = $actualQuote->getItemById($quoteItem->getId());
        $this->assertEquals(5.45, $itemReturned['price']);
        $this->assertEquals(16.35, $dbItem->getRowTotal());
        $this->assertEquals(16.35, $actualQuote->getSubtotal());
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ],
                    ],
                ],
            ],
            'negotiable_quote'
        )
    ]
    public function testAddItemProposedPriceDiscount(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');

        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $resourcePath = sprintf(self::RESOURCE_PATH, $quote->getId(), $quoteItem->getId());
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ]
        ];
        $requestData = [
            'cartItem' => [
                'item_id' => $quoteItem->getId(),
                'quote_id' => $quote->getId(),
                'qty' => 3,
                'extension_attributes' => [
                    'negotiable_quote_item' => [
                        'item_id' => $quoteItem->getId(),
                        'extension_attributes' => [
                            'negotiated_price_type' => 3,
                            'negotiated_price_value' => 4.55
                        ]
                    ]
                ]
            ]
        ];

        $itemReturned = $this->_webApiCall($serviceInfo, $requestData);
        $quoteRepository = $this->quoteRepositoryFactory->create();
        $actualQuote = $quoteRepository->get($quote->getId());
        $dbItem = $actualQuote->getItemById($quoteItem->getId());
        $this->assertEquals(4.55, $itemReturned['price']);
        $this->assertEquals(13.65, $dbItem->getRowTotal());
        $this->assertEquals(13.65, $actualQuote->getSubtotal());
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ],
                    ],
                ],
            ],
            'negotiable_quote'
        )
    ]
    public function testLineItemPercentDiscountException(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');

        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $resourcePath = sprintf(self::RESOURCE_PATH, $quote->getId(), $quoteItem->getId());
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ]
        ];
        $requestData = [
            'cartItem' => [
                'item_id' => $quoteItem->getId(),
                'quote_id' => $quote->getId(),
                'qty' => 3,
                'extension_attributes' => [
                    'negotiable_quote_item' => [
                        'item_id' => $quoteItem->getId(),
                        'extension_attributes' => [
                            'negotiated_price_type' => 1,
                            'negotiated_price_value' => 105
                        ]
                    ]
                ]
            ]
        ];

        $this->expectException('Exception');
        $this->expectExceptionMessage('Discount Percentage value should be > 0 and less than 100');
        $this->_webApiCall($serviceInfo, $requestData);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ],
                    ],
                ],
            ],
            'negotiable_quote'
        )
    ]
    public function testLineItemFixedDiscountException(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');

        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $resourcePath = sprintf(self::RESOURCE_PATH, $quote->getId(), $quoteItem->getId());
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ]
        ];
        $requestData = [
            'cartItem' => [
                'item_id' => $quoteItem->getId(),
                'quote_id' => $quote->getId(),
                'qty' => 3,
                'extension_attributes' => [
                    'negotiable_quote_item' => [
                        'item_id' => $quoteItem->getId(),
                        'extension_attributes' => [
                            'negotiated_price_type' => 2,
                            'negotiated_price_value' => 11
                        ]
                    ]
                ]
            ]
        ];

        $this->expectException('Exception');
        $this->expectExceptionMessage('Discount Amount cannot be greater than item price');
        $this->_webApiCall($serviceInfo, $requestData);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/negotiablequote_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'status' => 1,
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], 'quote'),
        DataFixture(Product::class, [], 'product'),
        DataFixture(
            AddProductToCart::class,
            ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 2],
            'item'
        ),
        DataFixture(
            ApplyQuoteConfigForCompany::class,
            ['company_id' => '$company.entity_id$', 'company_quote_enabled' => 1]
        ),
        DataFixture(
            NegotiableQuote::class,
            [
                NegotiableQuoteInterface::QUOTE_NAME => 'Quote #11',
                'quote' => [
                    'customer_id' => '$customer.id$',
                    CartInterface::KEY_ITEMS => [
                        [
                            CartItemInterface::KEY_SKU => '$product.sku$',
                            CartItemInterface::KEY_QTY => 2,
                        ],
                    ],
                ],
            ],
            'negotiable_quote'
        )
    ]
    public function testLineItemProposedPriceDiscountException(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');

        $quote = DataFixtureStorageManager::getStorage()->get('quote');
        $resourcePath = sprintf(self::RESOURCE_PATH, $quote->getId(), $quoteItem->getId());
        $serviceInfo = [
            'rest' => [
                'resourcePath' => $resourcePath,
                'httpMethod' => Request::HTTP_METHOD_PUT,
            ]
        ];
        $requestData = [
            'cartItem' => [
                'item_id' => $quoteItem->getId(),
                'quote_id' => $quote->getId(),
                'qty' => 3,
                'extension_attributes' => [
                    'negotiable_quote_item' => [
                        'item_id' => $quoteItem->getId(),
                        'extension_attributes' => [
                            'negotiated_price_type' => 3,
                            'negotiated_price_value' => 15
                        ]
                    ]
                ]
            ]
        ];
        $this->expectException('Exception');
        $this->expectExceptionMessage('Proposed price cannot be greater than item price');
        $this->_webApiCall($serviceInfo, $requestData);
    }
}
