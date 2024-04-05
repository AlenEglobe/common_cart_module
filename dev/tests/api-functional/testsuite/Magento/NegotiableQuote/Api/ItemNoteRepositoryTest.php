<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Api;

use Magento\Catalog\Test\Fixture\Product;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
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
use Magento\NegotiableQuote\Test\Fixture\NegotiableQuoteItemNote;
use Magento\NegotiableQuote\Test\Fixture\ApplyQuoteConfigForCompany;
use Magento\NegotiableQuote\Api\Data\ItemNoteInterface;
use Magento\TestFramework\Helper\Bootstrap;

class ItemNoteRepositoryTest extends WebapiAbstract
{
    /**
     * @var ItemNoteRepositoryInterface
     */
    private $itemNoteRepository;

    protected function setUp(): void
    {
        $this->_markTestAsRestOnly();
        $objectManager = Bootstrap::getObjectManager();
        $this->itemNoteRepository = $objectManager->get(ItemNoteRepositoryInterface::class);
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
            'quote'
        ),
        DataFixture(NegotiableQuoteItemNote::class, ['negotiable_quote_item_id' => '$item.id$'], 'note')
    ]
    public function testGetNegotiableQuoteItemNote(): void
    {
        /** @var ItemNoteInterface $note */
        $note = DataFixtureStorageManager::getStorage()->get('note');
        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/negotiable-cart-item-note/%d', $note->getNoteId()),
                'httpMethod' => Request::HTTP_METHOD_GET,
            ]
        ];
        $result = $this->_webApiCall($serviceInfo);

        $this->assertEquals($note->getNoteId(), $result['note_id']);
        $this->assertEquals($note->getNote(), $result['note']);
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
            'quote'
        )
    ]
    public function testSaveNegotiableQuoteItemNote(): void
    {
        $quoteItem = DataFixtureStorageManager::getStorage()->get('item');
        $company = DataFixtureStorageManager::getStorage()->get('company');

        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiable-cart-item-note/',
                'httpMethod' => Request::HTTP_METHOD_POST,
            ]
        ];
        $requestData = [
            'itemNote' => [
                'negotiable_quote_item_id' => $quoteItem->getId(),
                'creator_type' => ItemNoteInterface::CREATOR_TYPE_BUYER,
                'creator_id' => $company->getSuperUserId(),
                'note' => 'Line Item Note'
            ]
        ];

        $noteId = $this->_webApiCall($serviceInfo, $requestData);

        $actual = $this->itemNoteRepository->get($noteId);

        $this->assertEquals($quoteItem->getId(), $actual->getNegotiableQuoteItemId());
        $this->assertEquals('Line Item Note', $actual->getNote());
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
            'quote'
        ),
        DataFixture(NegotiableQuoteItemNote::class, ['negotiable_quote_item_id' => '$item.id$'], 'note')
    ]
    public function testDeleteNegotiableQuoteItemNote(): void
    {
        /** @var ItemNoteInterface $note */
        $note = DataFixtureStorageManager::getStorage()->get('note');
        $serviceInfo = [
            'rest' => [
                'resourcePath' => sprintf('/V1/negotiable-cart-item-note/%d', $note->getNoteId()),
                'httpMethod' => Request::HTTP_METHOD_DELETE,
            ]
        ];
        $this->_webApiCall($serviceInfo);

        try {
            $this->itemNoteRepository->get($note->getNoteId());
            $this->fail('Negotiable quote item note has not been removed.');
        } catch (\Exception $e) {
            $this->assertEquals(
                sprintf("Item note with id %d not found.", $note->getNoteId()),
                $e->getMessage()
            );
        }
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
            'quote'
        ),
        DataFixture(NegotiableQuoteItemNote::class, ['negotiable_quote_item_id' => '$item.id$'], 'note')
    ]
    public function testGetListNegotiableQuoteItemNote(): void
    {
        /** @var ItemNoteInterface $note */
        $note = DataFixtureStorageManager::getStorage()->get('note');
        $httpQuery = http_build_query([
            'searchCriteria' => [
                'filterGroups' => [
                    0 => [
                        'filters' => [
                            0 => [
                                'field' => 'note',
                                'value' => $note->getNote(),
                                'conditionType' => 'eq'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiable-cart-item-note/search?' . $httpQuery,
                'httpMethod' => Request::HTTP_METHOD_GET,
            ]
        ];
        $result = $this->_webApiCall($serviceInfo);

        $this->assertCount(1, $result['items']);
        $this->assertEquals($note->getNoteId(), $result['items'][0]['note_id']);
        $this->assertEquals($note->getNote(), $result['items'][0]['note']);
    }
}
