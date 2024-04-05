<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Block\Adminhtml\System\Config\WebsiteRestriction;

/**
 * Tests for shared catalog in config page.
 *
 * @magentoAppArea adminhtml
 */
class IsActiveTest extends \Magento\TestFramework\TestCase\AbstractBackendController
{
    /**
     * Checks that OAuth Section in the system config is loaded
     *
     * @magentoAdminConfigFixture dev/translate_inline/active_admin 1
     */
    public function testIsActiveSection()
    {
        $this->dispatch('backend/admin/system_config/edit/section/general/');
        $body = $this->getResponse()->getBody();
        $this->assertStringContainsString('id="general_restriction_is_active"', $body);
    }
}
