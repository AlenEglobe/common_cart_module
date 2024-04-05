<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\Company;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/company_customers.php');

$companyFactory = Bootstrap::getObjectManager()->create(\Magento\Company\Api\Data\CompanyInterfaceFactory::class);
$companyRepository = Bootstrap::getObjectManager()
    ->create(\Magento\Company\Api\CompanyRepositoryInterface::class);
$companies = [];
for ($companyId = 1; $companyId <= 5; $companyId++) {
    /** @var $company Company */
    $company = $companyFactory->create(
        [
            'data' => [
                'status' => \Magento\Company\Api\Data\CompanyInterface::STATUS_APPROVED,
                'company_name' => 'Company ' . $companyId,
                'legal_name' => 'Company legal name ' . $companyId,
                'company_email' => 'email' . $companyId . '@domain.com',
                'street' => 'Street ' . $companyId,
                'city' => 'City ' . $companyId,
                'country_id' => 'US',
                'region' => 'AL',
                'region_id' => 1,
                'postcode' => '22222',
                'telephone' => '2222222',
                'super_user_id' => $companyId,
                'customer_group_id' => 1
            ]
        ]
    );
    $companies[] = $companyRepository->save($company);
}
