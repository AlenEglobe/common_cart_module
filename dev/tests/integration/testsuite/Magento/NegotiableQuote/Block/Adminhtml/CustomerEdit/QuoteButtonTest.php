<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Block\Adminhtml\CustomerEdit;

use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Create\Draft;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\User\Test\Fixture\User;

/**
 * @magentoAppArea adminhtml
 */
class QuoteButtonTest extends AbstractBackendController
{
    /**
     * @var string
     */
    private $draftResource = Draft::ADMIN_RESOURCE;

    /**
     * @var string
     */
    protected $resource = 'Magento_Customer::manage';

    /**
     * @var string
     */
    protected $uri = 'backend/customer/index/edit';

    /**
     * Verify customer edit page contains create quote button when admin has permission and customer is a company user
     */
    #[
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        )
    ]
    public function testEditAction()
    {
        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->getRequest()->setParam('id', $customerId);
        $this->dispatch($this->uri);
        $body = $this->getResponse()->getBody();

        // verify
        $this->assertStringContainsString('data-ui-id="quote-button"', $body);
    }

    /**
     * Verify customer edit page doesn't contain create quote when the customer is an individual user
     */
    #[
        DataFixture(Customer::class, as: 'customer')
    ]
    public function testEditActionIndividualUser()
    {
        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->getRequest()->setParam('id', $customerId);
        $this->dispatch($this->uri);
        $body = $this->getResponse()->getBody();

        // verify
        $this->assertStringNotContainsString('data-ui-id="quote-button"', $body);
    }

    /**
     * Verify customer edit page doesn't contain create quotes button when admin doesn't have permission
     */
    #[
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        )
    ]
    public function testEditActionNoAccess()
    {
        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();
        Bootstrap::getObjectManager()->get(AclBuilder::class)
            ->getAcl()
            ->deny(\Magento\TestFramework\Bootstrap::ADMIN_ROLE_ID, $this->draftResource);
        $this->getRequest()->setParam('id', $customerId);
        $this->dispatch($this->uri);
        $body = $this->getResponse()->getBody();

        // verify
        $this->assertStringNotContainsString('data-ui-id="quote-button"', $body);
    }
}
