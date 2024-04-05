<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\NegotiableQuote\Service\V1;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Test\Fixture\AssignCustomer as AssignCustomerFixture;
use Magento\Company\Test\Fixture\Company as CompanyFixture;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\AdminTokenServiceInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\NegotiableQuote\Api\Data\CompanyQuoteConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Test\Fixture\Group as GroupFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config as ConfigFixture;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NegotiableQuoteDraftManagementTest extends WebapiAbstract
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var int
     */
    private $quoteId;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        if ($this->quoteId) {
            $quote = $this->quoteRepository->get($this->quoteId);
            $this->quoteRepository->delete($quote);
        }
        parent::tearDown();
    }

    #[
        AppArea('adminhtml'),
        ConfigFixture('btob/website_configuration/negotiablequote_active', true),
        DataFixture(GroupFixture::class, as: 'group'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$group.id$',], 'store'),
        DataFixture(CustomerFixture::class, as: 'admin_customer'),
        DataFixture(
            \Magento\User\Test\Fixture\User::class,
            [
                'password' => 'adminPassword123',
                'role_id' => 1,
            ],
            'user'
        ),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$admin_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            CustomerFixture::class,
            [
                'password' => 'customerPassword',
            ],
            'customer'
        ),
        DataFixture(
            AssignCustomerFixture::class,
            [
                CompanyCustomerInterface::COMPANY_ID => '$company.id$',
                CompanyCustomerInterface::CUSTOMER_ID => '$customer.id$',
            ],
        ),
    ]
    public function testCreateDraftByAdmin()
    {
        $userFixture = DataFixtureStorageManager::getStorage()->get('user');
        $adminTokenService = $this->objectManager->create(AdminTokenServiceInterface::class);
        $adminToken = $adminTokenService
            ->createAdminAccessToken($userFixture->getUsername(), 'adminPassword123');

        $quoteDraftServiceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/draft',
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $adminToken,
            ],
            'soap' => [
                'service' => 'negotiableQuoteNegotiableQuoteDraftManagementV1',
                'serviceVersion' => 'V1',
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

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        $this->quoteId = $result;

        $quote = $this->quoteRepository->get($this->quoteId);
        $negotiableQuote = $quote->getExtensionAttributes()->getNegotiableQuote();

        $this->assertEquals(NegotiableQuoteInterface::STATUS_DRAFT_BY_ADMIN, $negotiableQuote->getStatus());
        $this->assertEquals(0, $negotiableQuote->getBaseOriginalTotalPrice());
        $this->assertEquals($userFixture->getId(), $negotiableQuote->getCreatorId());
        $this->assertEquals(UserContextInterface::USER_TYPE_ADMIN, $negotiableQuote->getCreatorType());
        $this->assertTrue($negotiableQuote->getIsRegularQuote());

        $this->assertEquals($storeFixture->getId(), $quote->getStoreId());
        $this->assertEquals(0, $quote->getItemsQty());
        $this->assertEquals($quote->getCustomer()->getId(), $customerFixture->getId());

        $customerTokenService = $this->objectManager->create(CustomerTokenServiceInterface::class);
        $customerToken = $customerTokenService
            ->createCustomerAccessToken($customerFixture->getEmail(), 'customerPassword');

        $cartTotalServiceInfo = [
            'rest' => [
                'resourcePath' => str_replace(':cartId', $quote->getId(), '/V1/negotiable-carts/:cartId/totals'),
                'httpMethod' => Request::HTTP_METHOD_GET,
                'token' => $customerToken,
            ],
            'soap' => [
                'service' => 'negotiableQuoteCartTotalRepositoryV1',
                'serviceVersion' => 'V1',
                'operation' => 'negotiableQuoteCartTotalRepositoryV1' . 'get',
                'token' => $customerToken,
            ],
        ];

        // confirm that the draft quote is not available to the customer
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('#.*You are not allowed to do this\..*#');
        $arguments = [];
        if (TESTS_WEB_API_ADAPTER === self::ADAPTER_SOAP) {
            $arguments = ['cartId' => $this->quoteId];
        } elseif (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
            $this->expectExceptionCode(400);
        }
        $this->_webApiCall($cartTotalServiceInfo, $arguments);
    }

    #[
        AppArea('adminhtml'),
        ConfigFixture('btob/website_configuration/negotiablequote_active', true),
        ConfigFixture('customer/account_share/scope', 1),
        DataFixture(
            WebsiteFixture::class,
            [
                'code' => 'au_website',
            ],
            'au_website'
        ),
        DataFixture(GroupFixture::class, ['website_id' => '$au_website.id$'], 'au_group'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$au_group.id$',], 'au_store'),
        ConfigFixture(
            'btob/website_configuration/negotiablequote_active',
            false,
            ScopeInterface::SCOPE_WEBSITE,
            'au_website',
        ),
        DataFixture(WebsiteFixture::class, as: 'eu_website'),
        DataFixture(GroupFixture::class, ['website_id' => '$eu_website.id$'], 'eu_group'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$eu_group.id$',], 'eu_store'),
        DataFixture(GroupFixture::class, as: 'second_group'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$second_group.id$',], 'second_store'),
        DataFixture(
            StoreFixture::class,
            [
                'store_group_id' => '$second_group.id$',
                'is_active' => 0,
            ],
            'inactive_store'
        ),
        DataFixture(CustomerFixture::class, as: 'company_admin_customer'),
        DataFixture(
            \Magento\User\Test\Fixture\User::class,
            [
                'password' => 'adminPassword123',
                'role_id' => 1,
            ],
            'admin_user'
        ),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$company_admin_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$admin_user.id$',
            ],
            'company'
        ),
        DataFixture(CustomerFixture::class, as: 'pending_company_customer'),
        DataFixture(
            CompanyFixture::class,
            [
                CompanyInterface::SUPER_USER_ID => '$pending_company_customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$admin_user.id$',
                CompanyInterface::STATUS => CompanyInterface::STATUS_PENDING,
            ],
            'pending_company'
        ),
        DataFixture(CustomerFixture::class, as: 'company_customer'),
        DataFixture(
            CustomerFixture::class,
            [
                CustomerInterface::WEBSITE_ID => '$au_website.id$',
            ],
            'company_customer_au_website'
        ),
        DataFixture(
            AssignCustomerFixture::class,
            [
                CompanyCustomerInterface::COMPANY_ID => '$company.id$',
                CompanyCustomerInterface::CUSTOMER_ID => '$company_customer.id$',
            ],
        ),
        DataFixture(
            AssignCustomerFixture::class,
            [
                CompanyCustomerInterface::COMPANY_ID => '$company.id$',
                CompanyCustomerInterface::CUSTOMER_ID => '$company_customer_au_website.id$',
            ],
        ),
        DataFixture(CustomerFixture::class, as: 'customer'),
    ]
    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function testCreateDraftByAdminValidations()
    {
        $userFixture = DataFixtureStorageManager::getStorage()->get('admin_user');
        $companyCustomerFixture = DataFixtureStorageManager::getStorage()->get('company_customer');
        $storeFixture = DataFixtureStorageManager::getStorage()->get('second_store');

        $adminTokenService = $this->objectManager->create(AdminTokenServiceInterface::class);
        $adminToken = $adminTokenService
            ->createAdminAccessToken($userFixture->getUsername(), 'adminPassword123');
        $quoteDraftServiceInfo = [
            'rest' => [
                'resourcePath' => '/V1/negotiableQuote/draft',
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $adminToken,
            ],
            'soap' => [
                'service' => 'negotiableQuoteNegotiableQuoteDraftManagementV1',
                'serviceVersion' => 'V1',
                'operation' => 'negotiableQuoteNegotiableQuoteDraftManagementV1' . 'CreateDraftByAdmin',
                'token' => $adminToken,
            ],
        ];

        // validation: create draft quote for a customer that is not a company member
        $customerFixture = DataFixtureStorageManager::getStorage()->get('customer');
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $customerFixture->getId()],
                null,
                $storeFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Cannot create a quote. This customer account is not associated with a company.'
                    . ' Assign customer %customerId account to a company, and then try again.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                $this->assertEquals($customerFixture->getId(), $message['parameters']['customerId']);
            }
        } finally {
            if (isset($result)) {
                $this->fail('"Customer must be a company user" check has not been applied');
            }
        }

        // validation: create draft quote for a customer that does not have an account on the website
        $euStoreFixture = DataFixtureStorageManager::getStorage()->get('eu_store');
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $companyCustomerFixture->getId()],
                null,
                $euStoreFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Cannot create a quote because the customer does not have an account on this website.'
                    . ' Use %websiteId to create a quote for this customer.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                // default_website id is 1
                $this->assertEquals(1, $message['parameters']['websiteId']);
            }
        } finally {
            if (isset($result)) {
                $this->fail('"Customer has an account for the website" check has not been applied');
            }
        }

        // validation: create draft quote for a customer that assigned to the company that does not have quoting enabled
        $companyFixture = DataFixtureStorageManager::getStorage()->get('company');
        $companyRepository = $this->objectManager->create(CompanyRepositoryInterface::class);
        $company = $companyRepository->get($companyFixture->getId());
        /** @var CompanyQuoteConfigInterface $quoteConfig */
        $quoteConfig = $company->getExtensionAttributes()->getQuoteConfig();
        $quoteConfig->setIsQuoteEnabled(false);
        $companyRepository->save($company);
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $companyCustomerFixture->getId()],
                null,
                $storeFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Cannot create quote. The company %companyId account must be approved'
                    . ' by a store administrator and enabled for quoting.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                $this->assertEquals($companyFixture->getId(), $message['parameters']['companyId']);
            }
        } finally {
            $company->getExtensionAttributes()->getQuoteConfig()->setIsQuoteEnabled(true);
            $companyRepository->save($company);
            if (isset($result)) {
                $this->fail(
                    '"B2B quote functionality must be enabled at the company level" check has not been applied'
                );
            }
        }

        // validation: create draft quote for the website that does not have quoting enabled
        $auWebsiteFixture = DataFixtureStorageManager::getStorage()->get('au_website');
        $auStoreFixture = DataFixtureStorageManager::getStorage()->get('au_store');
        $companyCustomerAuWebsiteFixture = DataFixtureStorageManager::getStorage()->get('company_customer_au_website');
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $companyCustomerAuWebsiteFixture->getId()],
                null,
                $auStoreFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Quoting is not enabled for this website. Update website %websiteId to enable quoting.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                $this->assertEquals($auWebsiteFixture->getId(), $message['parameters']['websiteId']);
            }
        } finally {
            if (isset($result)) {
                $this->fail(
                    '"B2B quote functionality must be enabled at the website level" check has not been applied'
                );
            }
        }

        // validation: create draft quote for inactive customer
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $customer = $customerRepository->getById($companyCustomerFixture->getId());
        $customer->getExtensionAttributes()->getCompanyAttributes()
            ->setStatus(CompanyCustomerInterface::STATUS_INACTIVE);
        $customerRepository->save($customer);
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $companyCustomerFixture->getId()],
                null,
                $storeFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Cannot create quote for an inactive customer.'
                    . ' Edit the customer account %customerId to activate it.'
                    . ' Also, verify that the customer account is assigned to an approved company account'
                    . ' that has quoting enabled.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                $this->assertEquals($companyCustomerFixture->getId(), $message['parameters']['customerId']);
            }
        } finally {
            $customer->getExtensionAttributes()->getCompanyAttributes()
                ->setStatus(CompanyCustomerInterface::STATUS_ACTIVE);
            $customerRepository->save($customer);
            if (isset($result)) {
                $this->fail(
                    '"Customer must be active" check has not been applied'
                );
            }
        }

        // validation: create draft quote for not approved company
        $pendingCompanyCustomerFixture = DataFixtureStorageManager::getStorage()->get('pending_company_customer');
        $pendingCompanyFixture = DataFixtureStorageManager::getStorage()->get('pending_company');
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $pendingCompanyCustomerFixture->getId()],
                null,
                $storeFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Cannot create quote. The company %companyId account must be approved'
                    . ' by a store administrator and enabled for quoting.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                $this->assertEquals($pendingCompanyFixture->getId(), $message['parameters']['companyId']);
            }
        } finally {
            if (isset($result)) {
                $this->fail(
                    '"Company must be approved" check has not been applied'
                );
            }
        }

        // validation: create draft quote for inactive store
        $inactiveStoreFixture = DataFixtureStorageManager::getStorage()->get('inactive_store');
        try {
            $result = $this->_webApiCall(
                $quoteDraftServiceInfo,
                ['customerId' => $companyCustomerFixture->getId()],
                null,
                $inactiveStoreFixture->getCode(),
            );
            $this->quoteId = $result;
        } catch (\Exception $e) {
            $message = $this->getErrorMessage($e);
            $this->assertEquals(
                'Cannot create quote because %storeId is disabled.'
                    . ' Select an active (enabled) store and try again.',
                $message['message']
            );
            if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
                $this->assertEquals($inactiveStoreFixture->getId(), $message['parameters']['storeId']);
            }
        } finally {
            if (isset($result)) {
                $this->fail(
                    '"Store must be active" check has not been applied'
                );
            }
        }
    }

    /**
     * @param \Exception $e
     * @return array
     */
    public function getErrorMessage(\Exception $e): array
    {
        $message = [];
        if (TESTS_WEB_API_ADAPTER === self::ADAPTER_REST) {
            $this->assertEquals(400, $e->getCode());
            $message = json_decode($e->getMessage(), true);
            $this->assertArrayHasKey('message', $message);
            $this->assertArrayHasKey('parameters', $message);
        } elseif (TESTS_WEB_API_ADAPTER === self::ADAPTER_SOAP) {
            $this->assertEquals(0, $e->getCode());
            $this->assertNotEmpty($e->getMessage());
            $message['message'] = $e->getMessage();
        }

        return $message;
    }
}
