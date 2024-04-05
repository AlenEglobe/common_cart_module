<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Plugin\Customer\Model;

use Magento\Company\Api\Data\CompanyCustomerInterface;
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\EmailNotification;
use Magento\Customer\Model\EmailNotificationInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use Magento\Store\Model\App\Emulation;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Test\Fixture\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class EmailNotificationPluginTest extends TestCase
{
    /**
     * @var OrderGridCollection
     */
    private $emailNotification;

    /**
     * @var TransportBuilder|MockObject
     */
    private $transportBuilder;

    /**
     * @var Emulation|MockObject
     */
    private $emulation;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->transportBuilder = $this->getTransportBuilder();
        $this->emulation = $this->createMock(Emulation::class);
        $this->emailNotification = Bootstrap::getObjectManager()->create(
            EmailNotification::class,
            [
                'transportBuilder' => $this->transportBuilder,
                'emulation' => $this->emulation
            ]
        );

        $this->customerRepository = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(Customer::class, as: 'new_customer'),
        DataFixture(AssignCustomer::class, ['company_id' => '$company.id$', 'customer_id' => '$new_customer.id$'])
    ]
    public function testNewAccount(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('sendMessage');
        $this->transportBuilder->expects($this->once())
            ->method('getTransport')
            ->willReturn($transport);

        $customer = $this->customerRepository->getById(
            DataFixtureStorageManager::getStorage()->get('new_customer')->getId()
        );
        $this->emailNotification->newAccount(
            $customer,
            EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED,
            '',
            1
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        DataFixture(Customer::class, as: 'customer'),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(
            Customer::class,
            [
                CustomerInterface::EXTENSION_ATTRIBUTES_KEY => [
                    'company_attributes' => [
                        CompanyCustomerInterface::STATUS => CompanyCustomerInterface::STATUS_INACTIVE
                    ]
                ]
            ],
            'new_customer'
        ),
        DataFixture(AssignCustomer::class, ['company_id' => '$company.id$', 'customer_id' => '$new_customer.id$'])
    ]
    public function testNewAccountInactive(): void
    {
        $this->transportBuilder->expects($this->never())
            ->method('getTransport');

        $customer = $this->customerRepository->getById(
            DataFixtureStorageManager::getStorage()->get('new_customer')->getId()
        );
        $this->emailNotification->newAccount(
            $customer,
            EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED,
            '',
            1
        );
    }

    /**
     * @return TransportBuilder
     */
    private function getTransportBuilder(): TransportBuilder
    {
        $transportBuilder = $this->createMock(TransportBuilder::class);

        $methods = [
            'setTemplateIdentifier',
            'setTemplateOptions',
            'setTemplateVars',
            'setFrom',
            'addTo'
        ];

        foreach ($methods as $method) {
            $transportBuilder->expects($this->any())
                ->method($method)
                ->willReturn($transportBuilder);
        }

        return $transportBuilder;
    }
}
