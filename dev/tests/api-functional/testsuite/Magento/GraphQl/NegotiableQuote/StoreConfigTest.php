<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\NegotiableQuote;

use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Test coverage for negotiable quote settings in the store config
 */
class StoreConfigTest extends GraphQlAbstract
{
    const STORE_CONFIG_QUERY = <<<QUERY
{
    storeConfig {
        is_negotiable_quote_active
    }
}
QUERY;

    /**
     * Test that storeConfig.is_negotiable_quote_active is true when Negotiable Quote functionality is enabled.
     *
     * Assert that the value is a boolean type and not simply truthy.
     *
     * @magentoConfigFixture default/btob/website_configuration/negotiablequote_active 1
     * @throws \Exception
     */
    public function testStoreConfigWithNegotiableQuotesEnabled()
    {
        $response = $this->graphQlQuery(self::STORE_CONFIG_QUERY);
        self::assertArrayHasKey('is_negotiable_quote_active', $response['storeConfig']);
        self::assertEquals(true, $response['storeConfig']['is_negotiable_quote_active']);
    }

    /**
     * Test that storeConfig.is_negotiable_quote_active is false when Negotiable Quote functionality is disabled.
     *
     * Assert that the value is a boolean type and not simply falsy.
     *
     * @magentoConfigFixture default/btob/website_configuration/negotiablequote_active 0
     * @throws \Exception
     */
    public function testStoreConfigWithNegotiableQuotesDisabled()
    {
        $response = $this->graphQlQuery(self::STORE_CONFIG_QUERY);
        self::assertArrayHasKey('is_negotiable_quote_active', $response['storeConfig']);
        self::assertEquals(false, $response['storeConfig']['is_negotiable_quote_active']);
    }
}
