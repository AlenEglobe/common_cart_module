<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Magento\NegotiableQuote\Test\Fixture\QuoteIdMask;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Verify purchase order is created when order is placed
 */
class PlacePurchaseOrderTest extends GraphQlAbstract
{
    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    public function setUp(): void
    {
        $this->encoder = Bootstrap::getObjectManager()->get(Encoder::class);
        $this->getCustomerHeaders = Bootstrap::getObjectManager()->get(GetCustomerHeaders::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(
            Customer::class,
            [
                'addresses' => [
                    [
                        'country_id' => 'US',
                        'region_id' => 32,
                        'city' => 'Boston',
                        'street' => ['10 Milk Street'],
                        'postcode' => '02108',
                        'telephone' => '1234567890',
                        'default_billing' => true,
                        'default_shipping' => true
                    ]
                ]
            ],
            as: 'customer'
        ),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(PurchaseOrderCompanyConfig::class, ['company_id' => '$company.id$']),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], as: 'quote'),
        DataFixture(AddProductToCart::class, ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(SetBillingAddress::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetShippingAddress::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask')
    ]
    public function testPlacePurchaseOrder()
    {
        /** @var \Magento\Quote\Model\QuoteIdMask $quote */
        $maskedQuoteId = DataFixtureStorageManager::getStorage()->get('quoteIdMask')->getMaskedId();

        $result = $this->graphQlMutation(
            $this->getQuery($maskedQuoteId),
            [],
            '',
            $this->getCustomerHeaders->execute()
        );

        $this->assertTrue(isset($result['placePurchaseOrder']['purchase_order']['uid']));

        /** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
        $purchaseOrderRepository = Bootstrap::getObjectManager()->get(PurchaseOrderRepositoryInterface::class);
        $purchaseOrder = $purchaseOrderRepository->getById(
            $this->encoder->decode($result['placePurchaseOrder']['purchase_order']['uid'])
        );

        /** @var CustomerInterface $customer */
        $customer = DataFixtureStorageManager::getStorage()->get('customer');

        $this->assertEquals(
            [
                'placePurchaseOrder' => [
                    'purchase_order' => [
                        'uid' => $this->encoder->encode((string)$purchaseOrder->getEntityId()),
                        'number' => $purchaseOrder->getIncrementId(),
                        'created_by' => [
                            'email' => $customer->getEmail()
                        ],
                        'status' => 'APPROVED',
                        'quote' => [
                            'id' => $maskedQuoteId
                        ]
                    ]
                ]
            ],
            $result
        );

        $purchaseOrderRepository->delete($purchaseOrder);
    }

    /**
     * @param string $maskedQuoteId
     * @return string
     */
    private function getQuery(string $maskedQuoteId): string
    {
        return <<<QUERY
mutation {
  placePurchaseOrder(input: {cart_id: "{$maskedQuoteId}"}) {
    purchase_order {
      uid
      number
      created_by {
        email
      }
      status
      quote {
        id
      }
    }
  }
}
QUERY;
    }
}
