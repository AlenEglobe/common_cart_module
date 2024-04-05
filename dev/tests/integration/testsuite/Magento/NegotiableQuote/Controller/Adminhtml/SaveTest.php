<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Controller\Adminhtml;

use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Save;

/**
 * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_company_and_customer.php
 * @magentoDbIsolation enabled
 * @magentoConfigFixture default_store btob/website_configuration/negotiablequote_active true
 * @magentoAppArea adminhtml
 */
class SaveTest extends AbstractTest
{
    /**
     * @inheritDoc
     */
    protected $resource = Save::ADMIN_RESOURCE;

    /**
     * @inheritDoc
     */
    protected $uri = 'backend/quotes/quote/save';

    /**
     * @inheritDoc
     */
    protected $httpMethod = 'POST';
}
