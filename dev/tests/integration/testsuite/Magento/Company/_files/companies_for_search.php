<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Company\Model\Company;
use Magento\TestFramework\Helper\Bootstrap;

$companies = [
    [
        'name' => 'company 1',
        'status' => 1,
        'company_email' => 'email@domain.com',
        'comment' => 'comment 1',
    ],
    [
        'name' => 'company 2',
        'status' => 1,
        'company_email' => 'email@domain.com',
        'comment' => 'comment 5',
    ],
    [
        'name' => 'company 3',
        'status' => 1,
        'company_email' => 'email@domain.com',
        'comment' => 'comment 4',
    ],
    [
        'name' => 'company 4',
        'status' => 1,
        'company_email' => 'email@domain.com',
        'comment' => 'comment 2',
    ],
    [
        'name' => 'company 5',
        'status' => 2,
        'company_email' => 'email@domain.com',
        'comment' => 'comment 3',
    ],
];

foreach ($companies as $data) {
    /** @var $company Company */
    $company = Bootstrap::getObjectManager()->create(Company::class);
    $company->setCompanyName($data['name']);
    $company->setStatus($data['status']);
    $company->setCompanyEmail($data['company_email']);
    $company->setComment($data['comment']);
    $company->save();
}
