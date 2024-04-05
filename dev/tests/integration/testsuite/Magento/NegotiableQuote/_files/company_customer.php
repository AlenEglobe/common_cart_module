<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\CompanyInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Encryption\Encryptor;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/NegotiableQuote/_files/company_with_customer_for_quote.php');

$bootstrap = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
/** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
$customerRepository = $bootstrap->create(\Magento\Customer\Api\CustomerRepositoryInterface::class);
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $bootstrap->get(CustomerInterfaceFactory::class);
/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $bootstrap->get(DataObjectHelper::class);
/** @var Encryptor $encryptor */
$encryptor = $bootstrap->get(Encryptor::class);
/** @var CompanyInterfaceFactory $companyFactory */
$companyFactory = $bootstrap->get(CompanyInterfaceFactory::class);
/** @var CompanyInterface $company */
$company = $companyFactory->create()->load('email@companyquote.com', 'company_email');

$customer = $customerFactory->create();
$dataObjectHelper->populateWithArray(
    $customer,
    [
        'firstname' => 'Customer',
        'lastname' => 'Smith',
        'email' => 'customercompany22@example.com',
        'website_id' => 1,
        'extension_attributes' => [
            'company_attributes' => [
                'company_id' => $company->getId(),
                'status' => 1,
                'job_title' => 'Sales Rep'
            ]
        ]
    ],
    CustomerInterface::class
);
$customerRepository->save($customer, $encryptor->getHash('password', true));
