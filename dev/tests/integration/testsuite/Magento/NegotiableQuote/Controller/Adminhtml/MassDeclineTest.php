<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Controller\Adminhtml;

use Magento\NegotiableQuote\Controller\Adminhtml\Quote\MassDecline;

/**
 * @magentoAppArea adminhtml
 */
class MassDeclineTest extends AbstractTest
{
    /**
     * @inheritDoc
     */
    protected $resource = MassDecline::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/quotes/quote/massDecline';

    /**
     * @inheritDoc
     */
    protected $httpMethod = 'POST';
}
