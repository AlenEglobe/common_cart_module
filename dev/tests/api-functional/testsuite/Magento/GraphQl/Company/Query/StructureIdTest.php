<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Company\Query;

use Magento\Company\Api\Data\TeamInterface;
use Magento\Company\Model\Company\Structure;
use Magento\Company\Test\Fixture\Company;
use Magento\Company\Test\Fixture\Team;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\GraphQl\GetCustomerAuthenticationHeader;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Test for structure_id fields
 */
class StructureIdTest extends GraphQlAbstract
{
    private const QUERY_CUSTOMER = <<<QRY
{
    company {
        user(id: "%s") {
            structure_id
        }
        team(id: "%s") {
            structure_id
        }
    }
}
QRY;

    /**
     * @var Uid
     */
    private $uid;

    /**
     * @var GetCustomerAuthenticationHeader
     */
    private $getHeader;

    /**
     * @var Structure
     */
    private $structure;

    protected function setUp(): void
    {
        $this->uid = Bootstrap::getObjectManager()->get(Uid::class);
        $this->getHeader = Bootstrap::getObjectManager()->get(GetCustomerAuthenticationHeader::class);
        $this->structure = Bootstrap::getObjectManager()->get(Structure::class);
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
        DataFixture(Team::class, ['company_id' => '$company.id$'], 'team')
    ]
    public function testCustomer(): void
    {
        /** @var CustomerInterface $customer */
        $customer = DataFixtureStorageManager::getStorage()->get('customer');
        /** @var TeamInterface $team */
        $team = DataFixtureStorageManager::getStorage()->get('team');

        $this->assertEquals(
            [
                'company' => [
                    'user' => [
                        'structure_id' => $this->uid->encode(
                            (string) $this->structure->getStructureByCustomerId($customer->getId())->getId()
                        )
                    ],
                    'team' => [
                        'structure_id' => $this->uid->encode(
                            (string) $this->structure->getStructureByTeamId($team->getId())->getId()
                        )
                    ]
                ]
            ],
            $this->graphQlQuery(
                sprintf(
                    self::QUERY_CUSTOMER,
                    $this->uid->encode((string)$customer->getId()),
                    $this->uid->encode((string)$team->getId())
                ),
                [],
                '',
                $this->getHeader->execute($customer->getEmail())
            )
        );
    }
}
