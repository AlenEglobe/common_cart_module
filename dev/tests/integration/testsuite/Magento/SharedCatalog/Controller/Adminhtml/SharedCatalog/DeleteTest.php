<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Controller\Adminhtml\SharedCatalog;

use Magento\Customer\Test\Fixture\Customer;
use Magento\Company\Test\Fixture\CustomerGroup;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\SharedCatalog\Test\Fixture\SharedCatalog;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Test for class \Magento\SharedCatalog\Controller\Adminhtml\SharedCatalog\Delete
 *
 * @magentoAppArea adminhtml
 */
class DeleteTest extends AbstractBackendController
{

    /** Test Delete SharedCatalog
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @return void
     */
    #[
        Config('btob/website_configuration/sharedcatalog_active', 1),
        DataFixture(Customer::class, as: 'company_admin'),
        DataFixture(CustomerGroup::class, as: 'customer_group'),
        DataFixture(
            SharedCatalog::class,
            [
                'customer_group_id' => '$customer_group.id$'
            ],
            'shared_catalog'
        ),
    ]
    public function testDeleteSharedCatalog(): void
    {
        /** @var \Magento\SharedCatalog\Model\SharedCatalog $sharedCatalog */
        $sharedCatalog = DataFixtureStorageManager::getStorage()->get('shared_catalog');
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue(['shared_catalog_id' => $sharedCatalog->getId()]);
        $this->dispatch('backend/shared_catalog/sharedCatalog/delete');
        $successMessage = (string) __('The shared catalog was deleted successfully.');
        $this->assertSessionMessages($this->equalTo([$successMessage]));
    }

    /**
     * Test Delete Incorrect Shared Catalog ID
     *
     * @return void
     */
    public function testDeleteIncorrectSharedCatalog(): void
    {
        $incorrectId = 8;
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue(['shared_catalog_id' => $incorrectId]);
        $this->dispatch('backend/shared_catalog/sharedcatalog/delete');
        $errorMessage = (string) __('Requested Shared Catalog is not found');
        $this->assertSessionMessages(
            $this->equalTo([$errorMessage]),
            MessageInterface::TYPE_ERROR
        );
    }
}
