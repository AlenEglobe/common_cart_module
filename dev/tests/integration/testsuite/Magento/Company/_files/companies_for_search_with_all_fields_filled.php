<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/companies_for_search.php');

$objectManager = Bootstrap::getObjectManager();

$filterBuilder = $objectManager->create(FilterBuilder::class);
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);

$filters = [];

for ($i = 1; $i <= 5; ++$i) {
    $filters[] = $filterBuilder->setField(CompanyInterface::NAME)
        ->setValue("company $i")
        ->create();
}

$searchCriteriaBuilder->addFilters($filters);
$searchCriteria = $searchCriteriaBuilder->create();

$companyRepository = $objectManager->create(CompanyRepositoryInterface::class);

$searchResult = $companyRepository->getList($searchCriteria);

/** @var CompanyInterface[] $companies */
$companies = $searchResult->getItems();

$countryIds = ['AD', 'AE', 'AF', 'AG', 'AI'];

$i = 1;

$customerRepository = $objectManager->create(CustomerRepositoryInterface::class);

foreach ($companies as $company) {
    // create customer to assign as super user (admin) for company
    $customer = Bootstrap::getObjectManager()->create(CustomerInterface::class);
    $customer
        ->setEmail("customer$i@example.com")
        ->setGender($i % 3 + 1) // interpreted gender values are in range of 1-3
        ->setFirstname("First Name $i")
        ->setLastname("Last Name $i");

    $customerRepository->save($customer);

    $customer = $customerRepository->get("customer$i@example.com");

    if ($company->getStatus() == CompanyInterface::STATUS_REJECTED) {
        // setting required rejected data with validation bypassed (it was supposed to be set in companies_for_search)
        $company
            ->setRejectReason("reject reason $i")
            ->setRejectedAt('2000-01-01 00:00:00');

        $company->save();
    }

    // set everything on each company
    $company
        ->setSuperUserId($customer->getId())
        ->setCity("city $i")
        ->setComment("comment $i")
        ->setCompanyEmail("company$i@example.com")
        ->setCompanyName("company $i")
        ->setCountryId($countryIds[$i - 1])
        ->setCustomerGroupId(1)
        ->setLegalName("legal name $i")
        ->setPostcode("postcode $i")
        ->setRegion("region $i")
        ->setRegionId("$i")
        ->setResellerId("reseller id $i")
        ->setStreet("street $i")
        ->setTelephone("telephone $i")
        ->setVatTaxId("vat tax id $i");

    $companyRepository->save($company);

    // update company customer attributes (company_advanced_customer_entity)
    $companyCustomerAttributes = $customer->getExtensionAttributes()->getCompanyAttributes();

    $companyCustomerAttributes
        ->setCompanyId($company->getId())
        ->setJobTitle("Job Title $i");

    $customerRepository->save($customer);

    ++$i;
}
