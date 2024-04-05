<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\GiftCardRequisitionList;

use Magento\Framework\Exception\AuthenticationException;
use Magento\GiftCard\Model\Giftcard\Amount;
use Magento\GraphQl\RequisitionList\GetRequisitionList;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\RequisitionList\Model\RequisitionListRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for add requisition list items to requisition list mutation
 */
class AddGiftCardToRequisitionListTest extends GraphQlAbstract
{

    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var GetRequisitionList
     */
    private $getRequisitionList;

    /**
     * @var RequisitionListRepository
     */
    private $requisitionListRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->customerTokenService = $objectManager->get(CustomerTokenServiceInterface::class);
        $this->getRequisitionList = $objectManager->get(GetRequisitionList::class);
        $this->requisitionListRepository = $objectManager->get(RequisitionListRepository::class);
    }

    /**
     * Authentication header mapping
     *
     * @param string $username
     * @param string $password
     *
     * @return array
     *
     * @throws AuthenticationException
     */
    private function getHeaderAuthentication(
        string $username = 'customer@example.com',
        string $password = 'password'
    ): array {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($username, $password);

        return ['Authorization' => 'Bearer ' . $customerToken];
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/GiftCard/_files/gift_card_1.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     */
    public function testAddGiftCardToList()
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $requisitionList = $this->requisitionListRepository->get($requisitionListId);
        $listId = base64_encode((string)$requisitionListId);
        $sku = 'gift-card-with-amount';
        $queryProducts = $this->getQueryProducts($sku);
        $response = $this->graphQlQuery($queryProducts);
        $giftcardAmounts = $response['products']['items'][0]['giftcard_amounts'];
        $giftCardOptions = $response['products']['items'][0]['gift_card_options'];
        $uidMap = $this->mapUidsToGiftCardOptions($giftCardOptions);

        foreach ($giftcardAmounts as $giftcardAmount) {
            foreach ($this->giftcardOptionDataProvider() as $giftcardOptionData) {
                $giftcardOptionUid = $giftcardAmount['uid'];
                $query = $this->getAddProductsToRequisitionListQuery(
                    $sku,
                    1,
                    $listId,
                    (string) $giftcardOptionUid,
                    (string) $giftcardOptionData['giftcard_sender_name'],
                    (string) $giftcardOptionData['giftcard_sender_email'],
                    (string) $giftcardOptionData['giftcard_recipient_name'],
                    (string) $giftcardOptionData['giftcard_recipient_email'],
                    (string) $giftcardOptionData['giftcard_message'],
                    $uidMap
                );

                $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());

                self::assertArrayHasKey('addProductsToRequisitionList', $response);
                self::assertArrayHasKey('requisition_list', $response['addProductsToRequisitionList']);

                $list = $response['addProductsToRequisitionList']['requisition_list'];
                self::assertEquals($requisitionList->getName(), $list['name']);
                $giftcardItem = end($list['items']['items']);

                self::assertEquals($sku, $giftcardItem['product']['sku']);
                self::assertArrayHasKey('amount', $giftcardItem['gift_card_options']);
                self::assertEquals(
                    (float) $giftcardAmount['value'],
                    (float) $giftcardItem['gift_card_options']['amount']['value']
                );
                self::assertArrayHasKey('sender_name', $giftcardItem['gift_card_options']);
                self::assertEquals(
                    (float) $giftcardOptionData['giftcard_sender_name'],
                    (float) $giftcardItem['gift_card_options']['sender_name']
                );
                self::assertArrayHasKey('sender_email', $giftcardItem['gift_card_options']);
                self::assertEquals(
                    (float) $giftcardOptionData['giftcard_sender_email'],
                    (float) $giftcardItem['gift_card_options']['sender_email']
                );
                self::assertArrayHasKey('recipient_name', $giftcardItem['gift_card_options']);
                self::assertEquals(
                    (float) $giftcardOptionData['giftcard_recipient_name'],
                    (float) $giftcardItem['gift_card_options']['recipient_name']
                );
                self::assertArrayHasKey('recipient_email', $giftcardItem['gift_card_options']);
                self::assertEquals(
                    (float) $giftcardOptionData['giftcard_recipient_email'],
                    (float) $giftcardItem['gift_card_options']['recipient_email']
                );
                self::assertArrayHasKey('message', $giftcardItem['gift_card_options']);
                self::assertEquals(
                    (float) $giftcardOptionData['giftcard_message'],
                    (float) $giftcardItem['gift_card_options']['message']
                );
            }
        }
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/GiftCard/_files/gift_card_1.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     */
    public function testAddGiftCardToListWithCustomAmount()
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $requisitionList = $this->requisitionListRepository->get($requisitionListId);
        $listId = base64_encode((string)$requisitionListId);

        $sku = 'gift-card-with-amount';
        $queryProducts = $this->getQueryProducts($sku);
        $response = $this->graphQlQuery($queryProducts);
        $openAmountMin = (int) $response['products']['items'][0]['open_amount_min'];
        $openAmountMax = (int) $response['products']['items'][0]['open_amount_max'];
        $giftCardOptions = $response['products']['items'][0]['gift_card_options'];
        $uidMap = $this->mapUidsToGiftCardOptions($giftCardOptions);
        foreach ($this->giftcardOptionDataProvider() as $giftcardOptionData) {
            $giftcardCustomAmount = rand(
                $openAmountMin,
                $openAmountMax
            );
            $query = $this->getAddProductsToRequisitionListWithCustomAmountQuery(
                $sku,
                1,
                $listId,
                (string) $giftcardCustomAmount,
                (string) $giftcardOptionData['giftcard_sender_name'],
                (string) $giftcardOptionData['giftcard_sender_email'],
                (string) $giftcardOptionData['giftcard_recipient_name'],
                (string) $giftcardOptionData['giftcard_recipient_email'],
                (string) $giftcardOptionData['giftcard_message'],
                $uidMap
            );
            $response = $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
            self::assertArrayHasKey('addProductsToRequisitionList', $response);
            self::assertArrayHasKey('requisition_list', $response['addProductsToRequisitionList']);
            $list = $response['addProductsToRequisitionList']['requisition_list'];
            $giftcardItem = end($list['items']['items']);
            self::assertEquals($sku, $giftcardItem['product']['sku']);
            self::assertEquals($requisitionList->getName(), $list['name']);
            self::assertArrayHasKey('custom_giftcard_amount', $giftcardItem['gift_card_options']);
            self::assertArrayHasKey('sender_name', $giftcardItem['gift_card_options']);
            self::assertArrayHasKey('sender_email', $giftcardItem['gift_card_options']);
            self::assertArrayHasKey('recipient_name', $giftcardItem['gift_card_options']);
            self::assertArrayHasKey('recipient_email', $giftcardItem['gift_card_options']);
            self::assertArrayHasKey('message', $giftcardItem['gift_card_options']);
            self::assertEquals(
                (float) $giftcardCustomAmount,
                (float) $giftcardItem['gift_card_options']['custom_giftcard_amount']['value']
            );
        }
    }

    /**
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/GiftCard/_files/gift_card_1.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     */
    public function testAddGiftCardToListWithoutRequiredAttributes()
    {
        $requisitionListId = $this->getRequisitionList->execute('list two');
        $listId = base64_encode((string)$requisitionListId);
        $sku = 'gift-card-with-amount';
        $queryProducts = $this->getQueryProducts($sku);
        $response = $this->graphQlQuery($queryProducts);
        $giftcardAmounts = $response['products']['items'][0]['giftcard_amounts'];
        $giftCardOptions = $response['products']['items'][0]['gift_card_options'];
        $uidMap = $this->mapUidsToGiftCardOptions($giftCardOptions);

        foreach ($giftcardAmounts as $giftcardAmount) {
            foreach ($this->giftcardOptionDataProvider() as $giftcardOptionData) {
                $giftcardOptionUid = $giftcardAmount['uid'];
                $query = $this->getAddProductsToRequisitionListWithoutRequiredFieldsQuery(
                    $sku,
                    1,
                    $listId,
                    (string) $giftcardOptionUid,
                    (string) $giftcardOptionData['giftcard_sender_name'],
                    (string) $giftcardOptionData['giftcard_recipient_name'],
                    (string) $giftcardOptionData['giftcard_recipient_email'],
                    (string) $giftcardOptionData['giftcard_message'],
                    $uidMap
                );
                $this->expectException(ResponseContainsErrorsException::class);
                $this->expectExceptionMessage("Please add required gift card fields");

                $this->graphQlMutation($query, [], '', $this->getHeaderAuthentication());
                break;
            }
        }
    }

    /**
     * Get Uid by value
     *
     * @param int $value
     *
     * @return string
     */
    private function getUidByValue(int $value): string
    {
        $value = number_format($value, 4, '.', '');
        return base64_encode('giftcard_amount/' . $value);
    }

    private function getQueryProducts(string $sku): string
    {
        return <<<QUERY
{
  products(filter: {sku: {eq: "{$sku}"}}) {
    items {
      sku
      ... on GiftCardProduct {
        allow_open_amount
        open_amount_min
        open_amount_max
        giftcard_type
        is_redeemable
        lifetime
        allow_message
        message_max_length
        giftcard_amounts {
          uid
          value_id
          website_id
          value
          attribute_id
          website_value
        }
        gift_card_options {
          title
          required
          ... on CustomizableFieldOption {
            value: value {
              uid
            }
          }
        }
      }
    }
  }
}
QUERY;
    }

    /**
     * Giftcard options (sender_name, recipient_name, etc) to add giftcard to cart
     *
     * @return array
     */
    private function giftcardOptionDataProvider(): array
    {
        return [
            [
                'giftcard_sender_name' => 'Sender 1',
                'giftcard_sender_email' => 'sender1@email.com',
                'giftcard_recipient_name' => 'Recipient 1',
                'giftcard_recipient_email' => 'recipient1@email.com',
                'giftcard_message' => 'Message 1',
            ],
            [
                'giftcard_sender_name' => 'Sender 2',
                'giftcard_sender_email' => 'sender2@email.com',
                'giftcard_recipient_name' => 'Recipient 2',
                'giftcard_recipient_email' => 'recipient2@email.com',
                'giftcard_message' => 'Message 2',
            ]
        ];
    }

    /**
     * Iterable for creating uid array map
     *
     * @param array $giftCardOptions
     * @return array
     */
    public function mapUidsToGiftCardOptions($giftCardOptions)
    {
        $uidMap = [];
        foreach ($giftCardOptions as $giftCardOption) {
            $uidMap[$giftCardOption['title']] = $giftCardOption['value']['uid'];
        }
        return $uidMap;
    }

    /**
     * Returns GraphQl mutation string with custom amount
     *
     * @param string $sku
     * @param int $qty
     * @param string $requisitionListId
     * @param string $customAmountValue
     * @param string $senderName
     * @param string $senderEmail
     * @param string $recipientName
     * @param string $recipientEmail
     * @param string $message
     * @param array $uidMap
     * @return string
     */
    private function getAddProductsToRequisitionListWithCustomAmountQuery(
        string $sku,
        int $qty,
        string $requisitionListId,
        string $customAmountValue,
        string $senderName,
        string $senderEmail,
        string $recipientName,
        string $recipientEmail,
        string $message,
        array $uidMap
    ): string {
        return <<<MUTATION
mutation {
  addProductsToRequisitionList(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItems: [
      {
        sku: "{$sku}"
        quantity: {$qty}
        entered_options: [{
          uid: "{$uidMap['Custom Giftcard Amount']}"
      	  value: "{$customAmountValue}"
        }, {
          uid: "{$uidMap['Sender Name']}"
          value: "{$senderName}"
        }, {
          uid: "{$uidMap['Sender Email']}"
          value: "{$senderEmail}"
      	}, {
      	  uid: "{$uidMap['Recipient Name']}"
          value: "{$recipientName}"
      	}, {
          uid: "{$uidMap['Recipient Email']}"
          value: "{$recipientEmail}"
        }, {
      	  uid: "{$uidMap['Message']}"
          value: "{$message}"
      	}]
      }
    ]
) {
    requisition_list {
        uid
        name
        items_count
        description
        updated_at
        items {
            items {
                uid
                quantity
                product {
                  sku
                }
                ... on GiftCardRequisitionListItem {
                    gift_card_options {
                        message
                        sender_name
                        sender_email
                        recipient_name
                        recipient_email
                        custom_giftcard_amount {
                          value
                          currency
                        }
                    }
                }
            }
        }
    }
  }
}
MUTATION;
    }

    /**
     * Returns GraphQl mutation string
     *
     * @param string $sku
     * @param int $qty
     * @param string $requisitionListId
     * @param string $giftcardOptionUid
     * @param string $senderName
     * @param string $senderEmail
     * @param string $recipientName
     * @param string $recipientEmail
     * @param string $message
     * @param array $uidMap
     * @return string
     */
    private function getAddProductsToRequisitionListQuery(
        string $sku,
        int $qty,
        string $requisitionListId,
        string $giftcardOptionUid,
        string $senderName,
        string $senderEmail,
        string $recipientName,
        string $recipientEmail,
        string $message,
        array $uidMap
    ): string {
        return <<<MUTATION
mutation {
  addProductsToRequisitionList(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItems: [
      {
        sku: "{$sku}"
        quantity: {$qty}
        selected_options: [
          "{$giftcardOptionUid}"
        ]
        entered_options: [{
          uid: "{$uidMap['Sender Name']}"
          value: "{$senderName}"
        }, {
          uid: "{$uidMap['Sender Email']}"
          value: "{$senderEmail}"
      	}, {
      	  uid: "{$uidMap['Recipient Name']}"
          value: "{$recipientName}"
      	}, {
          uid: "{$uidMap['Recipient Email']}"
          value: "{$recipientEmail}"
        }, {
      	  uid: "{$uidMap['Message']}"
          value: "{$message}"
      	}]
      }
    ]
) {
    requisition_list {
        uid
        name
        items_count
        description
        updated_at
        items {
            items {
                uid
                quantity
                product {
                  sku
                }
                ... on GiftCardRequisitionListItem {
                    gift_card_options {
                        message
                        sender_name
                        sender_email
                        recipient_name
                        recipient_email
                        amount {
                          value
                          currency
                        }
                    }
                }
            }
        }
    }
  }
}
MUTATION;
    }

    /**
     * Returns GraphQl addProductToRequisitionList mutation string without giftcard required params
     *
     * @param string $sku
     * @param int $qty
     * @param string $requisitionListId
     * @param string $giftcardOptionUid
     * @param string $senderName
     * @param string $recipientName
     * @param string $recipientEmail
     * @param string $message
     * @param array $uidMap
     * @return string
     */
    private function getAddProductsToRequisitionListWithoutRequiredFieldsQuery(
        string $sku,
        int $qty,
        string $requisitionListId,
        string $giftcardOptionUid,
        string $senderName,
        string $recipientName,
        string $recipientEmail,
        string $message,
        array $uidMap
    ): string {
        return <<<MUTATION
mutation {
  addProductsToRequisitionList(
    requisitionListUid: "{$requisitionListId}",
    requisitionListItems: [
      {
        sku: "{$sku}"
        quantity: {$qty}
        selected_options: [
          "{$giftcardOptionUid}"
        ]
        entered_options: [{
          uid: "{$uidMap['Sender Name']}"
          value: "{$senderName}"
        }, {
      	  uid: "{$uidMap['Recipient Name']}"
          value: "{$recipientName}"
      	}, {
          uid: "{$uidMap['Recipient Email']}"
          value: "{$recipientEmail}"
        }, {
      	  uid: "{$uidMap['Message']}"
          value: "{$message}"
      	}]
      }
    ]
) {
    requisition_list {
        uid
        name
        items_count
        description
        updated_at
        items {
            items {
                uid
                quantity
                ... on GiftCardRequisitionListItem {
                    gift_card_options {
                        message
                        sender_name
                        sender_email
                        recipient_name
                        recipient_email
                        amount {
                          value
                          currency
                        }
                    }
                }
            }
        }
    }
  }
}
MUTATION;
    }
}
