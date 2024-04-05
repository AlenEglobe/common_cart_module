<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Model;

use Magento\Backend\Model\Auth\Session;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\DateTime;
use Magento\NegotiableQuote\Api\Data\HistoryInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteDraftManagementInterface;
use Magento\NegotiableQuote\Api\NegotiableQuoteRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Model\User;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NegotiableQuoteDraftManagementTest extends TestCase
{
    /**
     * @var NegotiableQuoteDraftManagement
     */
    private $negotiableQuoteDraftManagement;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Quote|null
     */
    private ?Quote $testQuote = null;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->negotiableQuoteDraftManagement =
            $this->objectManager->create(NegotiableQuoteDraftManagementInterface::class);
    }

    #[
        AppArea('adminhtml'),
        DbIsolation(false),
        Config('btob/website_configuration/negotiablequote_active', '1', ScopeInterface::SCOPE_WEBSITE),
        DataFixture(\Magento\Store\Test\Fixture\Group::class, as: 'group'),
        DataFixture(\Magento\Store\Test\Fixture\Store::class, ['store_group_id' => '$group.id$',], 'store'),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'admin_customer'),
        DataFixture(\Magento\User\Test\Fixture\User::class, as: 'user'),
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
    ]
    /**
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCreateDraftByAdmin()
    {
        /** @var User $user */
        $user = DataFixtureStorageManager::getStorage()->get('user');
        $customerId = (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $storeId = DataFixtureStorageManager::getStorage()->get('store')->getId();
        $cartManagement = $this->objectManager->get(CartManagementInterface::class);
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $session = $this->objectManager->get(Session::class);

        /** the store context is used when creating quote */
        $storeManager->setCurrentStore($storeId);
        /** the user context is needed for proper initialization */
        $session->setUser($user);
        $cartId = $cartManagement->createEmptyCartForCustomer($customerId);
        $quoteId = $this->negotiableQuoteDraftManagement->createDraftByAdmin($customerId);

        $this->assertGreaterThan($cartId, $quoteId);

        $this->testQuote = $this->objectManager->get(Quote::class);
        /**
         * We use this method instead of repository to retrieve raw data from the DB.
         * Plugins update quote attribute values on load.
         */
        $this->testQuote->loadByIdWithoutStore($quoteId);

        /** check the quote attribute values */
        $this->assertEquals(0, $this->testQuote->getItemsCount());
        $this->assertEquals($customerId, $this->testQuote->getCustomer()->getId());
        $this->assertEquals($storeId, $this->testQuote->getStoreId());
        /** returns an int value, but must be bool */
        $this->assertFalse((bool)$this->testQuote->getIsActive());

        $negotiableQuoteRepository = $this->objectManager->get(NegotiableQuoteRepositoryInterface::class);
        $negotiableQuote = $negotiableQuoteRepository->getById($quoteId);

        /** check the negotiable quote attribute values */
        $this->assertSame(NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN, $negotiableQuote->getStatus());
        $this->assertNotEmpty($negotiableQuote->getQuoteName());
        /** returns an int value, but must be bool */
        $this->assertTrue((bool)$negotiableQuote->getIsRegularQuote());

        $historyManagement = $this->objectManager->get(HistoryManagementInterface::class);
        $quoteHistoryItems = $historyManagement->getQuoteHistory($quoteId);
        $this->assertCount(1, $quoteHistoryItems);
        /** @var HistoryInterface $quoteHistory */
        $quoteHistory = reset($quoteHistoryItems);
        $this->assertEquals($user->getId(), $quoteHistory->getAuthorId());
        $this->assertTrue($quoteHistory->getIsSeller());
    }

    #[
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'customer'),
    ]
    /**
     * @magentoAppArea adminhtml
     * @throws InputException
     * @throws LocalizedException
     */
    public function testCreateDraftByAdminIndividualUserValidationError()
    {
        $this->expectException(LocalizedException::class);
        $msgRegexp = '#Cannot create a quote\. This customer account is not associated with a company\. '
            . '<a href="http:\/\/localhost\/index\.php\/backend\/customer\/index\/edit\/id\/\d+\/key\/[a-z0-9]+\/">'
            . 'Edit<\/a> the customer account to assign a company, and then try again\.#';
        $this->expectExceptionMessageMatches($msgRegexp);
        $customerId = (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->negotiableQuoteDraftManagement->createDraftByAdmin($customerId);
    }

    #[
        AppArea('adminhtml'),
        DbIsolation(false),
        Config('btob/website_configuration/negotiablequote_active', '1', ScopeInterface::SCOPE_WEBSITE),
        DataFixture(\Magento\Store\Test\Fixture\Group::class, as: 'group'),
        DataFixture(\Magento\Store\Test\Fixture\Store::class, ['store_group_id' => '$group.id$',], 'store'),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'admin_customer'),
        DataFixture(\Magento\User\Test\Fixture\User::class, as: 'user'),
        DataFixture(
            \Magento\Company\Test\Fixture\Company::class,
            [
                CompanyInterface::SUPER_USER_ID => '$admin_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
                CompanyInterface::NAME => 'Test Company with non-approved status',
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
    ]
    public function testCreateDraftByAdminNonApprovedCompanyValidationError()
    {
        $statuses = [
            CompanyInterface::STATUS_PENDING,
            CompanyInterface::STATUS_BLOCKED,
            CompanyInterface::STATUS_REJECTED
        ];

        $companyFixture = DataFixtureStorageManager::getStorage()->get('company');
        $messagePattern = '#Cannot create quote. The <a href="http://localhost/index.php/backend/company/index'
            .'/edit/id/' . $companyFixture->getId() .'/key/\w+/">' . $companyFixture->getData(CompanyInterface::NAME)
            .'</a> account must be approved by a store administrator and enabled for quoting.#';
        $customerId = (int)DataFixtureStorageManager::getStorage()->get('customer')->getId();

        $companyRepository = $this->objectManager->create(CompanyRepositoryInterface::class);
        $company = $companyRepository->get($companyFixture->getId());
        foreach ($statuses as $status) {
            $company->setStatus($status);
            if ($status === CompanyInterface::STATUS_REJECTED) {
                $company->setRejectedAt((new \DateTime())->format(DateTime::DATETIME_PHP_FORMAT));
                $company->setRejectReason('Test rejection reason');
            }
            $companyRepository->save($company);
            try {
                $this->negotiableQuoteDraftManagement->createDraftByAdmin($customerId);
                $this->fail('Validation error was not thrown');
            } catch (LocalizedException $e) {
                $this->assertMatchesRegularExpression($messagePattern, $e->getMessage());
            }
        }
    }

    protected function tearDown(): void
    {
        /** removal of the quote created in the test */
        if ($this->testQuote) {
            $cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
            $cartRepository->delete($this->testQuote);
            $this->testQuote = null;
        }
        parent::tearDown();
    }
}
