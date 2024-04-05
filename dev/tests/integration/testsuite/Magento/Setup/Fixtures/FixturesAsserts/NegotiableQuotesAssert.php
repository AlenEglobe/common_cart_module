<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Setup\Fixtures\FixturesAsserts;

/**
 * Performs assertion that generated negotiable quotes are valid
 * after running setup:performance:generate-fixtures command
 */
class NegotiableQuotesAssert
{
    /**
     * @var \Magento\NegotiableQuote\Model\NegotiableQuoteRepository
     */
    private $negotiableQuoteRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param \Magento\NegotiableQuote\Model\NegotiableQuoteRepository $negotiableQuoteRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        \Magento\NegotiableQuote\Model\NegotiableQuoteRepository $negotiableQuoteRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->negotiableQuoteRepository = $negotiableQuoteRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Asserts that generated negotiable quotes are valid
     * Checks only for quotes count for now
     *
     * @return bool
     * @throws \AssertionError
     */
    public function assert()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $negotiableQuotes = $this->negotiableQuoteRepository->getList($searchCriteria)->getItems();

        if (20 !== count($negotiableQuotes)) {
            throw new \AssertionError('Negotiable quotes amount is wrong');
        }

        return true;
    }
}
