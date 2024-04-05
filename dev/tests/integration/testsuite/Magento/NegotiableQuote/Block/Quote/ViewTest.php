<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Block\Quote;

use Magento\Backend\Model\Auth;
use Magento\Framework\View\Element\BlockInterface;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Decline;
use Magento\NegotiableQuote\Controller\Adminhtml\Quote\PrintAction;
use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Save;
use Magento\NegotiableQuote\Controller\Adminhtml\Quote\Send;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\Acl;
use Magento\NegotiableQuote\Model\Restriction\RestrictionInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\NegotiableQuote\Block\Adminhtml\Quote\View;
use Magento\Framework\View\LayoutInterface;

/**
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ViewTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var LayoutInterface
     */
    private $layout;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $objectManager = Bootstrap::getObjectManager();
        $this->acl = $objectManager->get(AclBuilder::class)->getAcl();
        $this->layout = Bootstrap::getObjectManager()->get(LayoutInterface::class);

        $objectManager->get(Auth::class)->login(
            \Magento\TestFramework\Bootstrap::ADMIN_NAME,
            \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD
        );
    }

    /**
     * @param string $aclResource
     * @param string $expectedStringToSee
     * @dataProvider buttonVisibilityDataProvider
     */
    public function testButtonVisibleWhenAclRoleIsEnabled($aclResource, $expectedStringToSee)
    {
        $this->acl->allow(\Magento\TestFramework\Bootstrap::ADMIN_ROLE_ID, $aclResource);

        $negotiableQuote = Bootstrap::getObjectManager()->get(NegotiableQuoteInterface::class);
        $negotiableQuote->setStatus(NegotiableQuoteInterface::STATUS_PROCESSING_BY_ADMIN);
        $quote = Bootstrap::getObjectManager()->get(Quote::class);
        $quote->getExtensionAttributes()->setNegotiableQuote($negotiableQuote);
        $restriction = Bootstrap::getObjectManager()->get(RestrictionInterface::class);
        $restriction->setQuote($quote);

        $html = $this->createBlockInstance(['restriction' => $restriction])->getButtonsHtml();

        $this->assertStringContainsString($expectedStringToSee, $html);
    }

    /**
     * @param string $aclResource
     * @param string $expectedStringToNotSee
     * @dataProvider buttonVisibilityDataProvider
     */
    public function testButtonNotVisibleWhenAclRoleIsDisabled(string $aclResource, string $expectedStringToNotSee)
    {
        $this->acl->deny(\Magento\TestFramework\Bootstrap::ADMIN_ROLE_ID, $aclResource);

        $html = $this->createBlockInstance()->getButtonsHtml();

        $this->assertStringNotContainsString($expectedStringToNotSee, $html);
    }

    /**
     * @return array
     */
    public function buttonVisibilityDataProvider(): array
    {
        return [
            [Decline::ADMIN_RESOURCE, 'quote-view-decline-button'],
            [PrintAction::ADMIN_RESOURCE, 'quote_print'],
            [Save::ADMIN_RESOURCE, 'quote_save'],
            [Send::ADMIN_RESOURCE, 'quote_send'],
        ];
    }

    /**
     * @param array $arguments
     * @return BlockInterface
     */
    private function createBlockInstance(array $arguments = []): BlockInterface
    {
        return $this->layout->createBlock(View::class, '', $arguments);
    }
}
