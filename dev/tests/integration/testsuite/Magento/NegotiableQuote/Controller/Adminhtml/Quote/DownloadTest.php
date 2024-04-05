<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Controller\Adminhtml\Quote;

use Magento\Framework\App\Request\Http;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoAppArea adminhtml
 */
class DownloadTest extends AbstractBackendController
{
    /**
     * The resource used to authorize action
     *
     * @var string
     */
    protected $resource = 'Magento_NegotiableQuote::view_quotes';

    /**
     * The uri at which to access the controller
     *
     * @var string
     */
    protected $uri = 'backend/quotes/quote/download/';

    /**
     * @var string|null
     */
    protected $httpMethod = Http::METHOD_GET;

    /**
     * @inheritdoc
     * @see \Magento\Backend\App\Response\Http\FileFactory::create
     */
    public function testAclHasAccess()
    {
        $this->uri = null;
        parent::testAclHasAccess();
    }
}
