<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Company\Model\Email;

use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use PHPUnit\Framework\TestCase;

/**
 * Company email sender test
 */
class SenderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * Test custom email logo is contained within the message body of a new company email sent to the corresponding
     * sales representative
     *
     * Given a custom email logo for the default store
     * When a new company is created
     * Then the email that the sales representative receives contains the custom email logo instead of the default logo
     *
     * @magentoDataFixture Magento/Company/_files/email_logo.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     * @magentoConfigFixture default_store design/email/logo magento_logo.jpg
     */
    public function testCustomEmailLogoIsPresentInEmailSentToSalesRepresentativeWhenCompanyIsCreated(): void
    {
        /** @var TransportBuilderMock $transportBuilder */
        $transportBuilder = $this->objectManager->get(TransportBuilderMock::class);
        $message = $transportBuilder->getSentMessage();
        $this->assertNotNull($message);
        $this->assertStringContainsString(
            'magento_logo.jpg',
            $message->getBody()->getParts()[0]->getRawContent(),
            'Expected text wasn\'t found in message.'
        );
    }
}
