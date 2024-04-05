<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyCredit\Controller\Adminhtml\Index;

use Magento\CompanyCredit\Controller\Adminhtml\Index\Reimburse;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractBackendController;

class ReimburseTest extends AbstractBackendController
{
    /**
     * @inheritDoc
     */
    protected $resource = Reimburse::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/credit/index/reimburse';

    /**
     * @inheritDoc
     */
    protected $httpMethod = HttpRequest::METHOD_POST;
}
