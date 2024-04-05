<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Controller\Adminhtml;

use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Send;

/**
 * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_company_and_customer.php
 * @magentoDbIsolation enabled
 * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
 * @magentoAppArea adminhtml
 */
class SendTest extends AbstractTest
{
    /**
     * @inheritDoc
     */
    protected $resource = Send::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/quotes/quote/send';

    /**
     * @inheritDoc
     */
    protected $httpMethod = 'POST';
}
