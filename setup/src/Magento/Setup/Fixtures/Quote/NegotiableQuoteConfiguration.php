<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup\Fixtures\Quote;

/**
 * Configuration for generating negotiable quotes.
 */
class NegotiableQuoteConfiguration extends QuoteConfiguration
{
    /**
     * Mappings for number of different types of products in negotiable quote.
     *
     * @var array
     */
    protected $_globalMap = [
        'quote_simple_product_count_to' => 'simple_count_to',
        'quote_simple_product_count_from' => 'simple_count_from',
        'quote_configurable_product_count_to' => 'configurable_count_to',
        'quote_configurable_product_count_from' => 'configurable_count_from',
        'quote_big_configurable_product_count_to' => 'big_configurable_count_to',
        'quote_big_configurable_product_count_from' => 'big_configurable_count_from',
    ];

    /**
     * @var string
     */
    protected $fixtureDataFilename = 'quote_fixture_data.json';
}
