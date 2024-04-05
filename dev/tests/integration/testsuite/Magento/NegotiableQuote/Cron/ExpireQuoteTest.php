<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Cron;

use Magento\Framework\Stdlib\DateTime;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\NegotiableQuote\Model\Expiration;
use Magento\NegotiableQuote\Model\Expired\MerchantNotifier;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea crontab
 */
class ExpireQuoteTest extends TestCase
{
    /**
     * @var MerchantNotifier|MockObject|null
     */
    private $merchantNotifierMock;

    /**
     * @var NegotiableQuoteRepositoryInterface|null
     */
    private $repository;

    /**
     * @var ExpireQuote|null
     */
    private $expireQuoteCron;
    /**
     * @var DateTime\TimezoneInterface
     */
    private $locale;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->merchantNotifierMock = $this->getMockBuilder(MerchantNotifier::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->locale = $objectManager->get(DateTime\TimezoneInterface::class);
        $this->repository = $objectManager->create(NegotiableQuoteRepositoryInterface::class);
        $this->expireQuoteCron = $objectManager->create(
            ExpireQuote::class,
            ['merchantNotifier' => $this->merchantNotifierMock]
        );
    }

    #[
        DataFixture(\Magento\Catalog\Test\Fixture\Product::class, as: 'product'),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'customer'),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
            ],
            'negotiable_quote_1'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
            ],
            'negotiable_quote_2'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
            ],
            'negotiable_quote_3'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote::class,
            [
                'quote' => ['customer_id' => '$customer.id$'],
            ],
            'negotiable_quote_draft'
        ),
    ]
    /**
     * @magentoConfigFixture current_website quote/general/default_expiration_period 20
     * @magentoConfigFixture current_website quote/general/default_expiration_period_time week
     */
    public function testExecute()
    {
        $today = $this->locale->date()->format(DateTime::DATE_PHP_FORMAT);
        $tomorrow = $this->locale
            ->date(new \DateTime('+1 day'), null, true)
            ->format(DateTime::DATE_PHP_FORMAT);

        /** @var NegotiableQuoteInterface $quote1 */
        $quote1 = DataFixtureStorageManager::getStorage()->get('negotiable_quote_1');
        $quote1->setExpirationPeriod($today);
        $this->repository->save($quote1);

        /** @var NegotiableQuoteInterface $quote2 */
        $quote2 = DataFixtureStorageManager::getStorage()->get('negotiable_quote_2');
        $quote2->setExpirationPeriod(Expiration::DATE_QUOTE_NEVER_EXPIRES);
        $this->repository->save($quote2);

        /** @var NegotiableQuoteInterface $quote3 */
        $quote3 = DataFixtureStorageManager::getStorage()->get('negotiable_quote_3');
        $quote3->setExpirationPeriod($tomorrow);
        $this->repository->save($quote3);

        /** @var NegotiableQuoteInterface $quoteDraft */
        $quoteDraft = DataFixtureStorageManager::getStorage()->get('negotiable_quote_draft');
        $quoteDraft->setExpirationPeriod($today);
        $this->repository->save($quoteDraft);

        $this->merchantNotifierMock->expects($this->exactly(2))
            ->method('sendNotification')
            ->withConsecutive(
                [$quote1->getQuoteId()],
                [$quoteDraft->getQuoteId()]
            );

        $this->expireQuoteCron->execute();

        $newDate = $this->locale->date(new \DateTime('+20 weeks'))->format(DateTime::DATE_PHP_FORMAT);
        $this->assertEquals(
            $newDate,
            $this->repository->getById($quote1->getId())->getExpirationPeriod()
        );
        $this->assertEquals(
            Expiration::DATE_QUOTE_NEVER_EXPIRES,
            $this->repository->getById($quote2->getId())->getExpirationPeriod()
        );
        $this->assertEquals(
            $tomorrow,
            $this->repository->getById($quote3->getId())->getExpirationPeriod()
        );
        $this->assertEquals(
            $newDate,
            $this->repository->getById($quoteDraft->getId())->getExpirationPeriod()
        );
    }
}
