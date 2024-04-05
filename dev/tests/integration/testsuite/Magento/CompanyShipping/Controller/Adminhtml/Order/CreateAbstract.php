<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Controller\Adminhtml\Order;

use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Config\Model\Config\Factory as ConfigFactory;

/**
 * Abstract class for Create Company Shipment Order
 */
class CreateAbstract extends AbstractBackendController
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    protected $uri = 'backend/sales/order_create';

    protected $resource = 'Magento_Sales::create';

    /**
     * @inheritDoc
     *
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configFactory = $this->_objectManager->get(ConfigFactory::class);
    }

    /**
     * Update scope config settings
     * @param array $configData
     * @throws \Exception
     */
    protected function setConfigValues($configData)
    {
        foreach ($configData as $scope => $data) {
            foreach ($data as $scopeCode => $scopeData) {
                foreach ($scopeData as $path => $value) {
                    $config = $this->configFactory->create();
                    $config->setScope($scope);

                    if ($scope == ScopeInterface::SCOPE_WEBSITES) {
                        $config->setWebsite($scopeCode);
                    }

                    if ($scope == ScopeInterface::SCOPE_STORES) {
                        $config->setStore($scopeCode);
                    }

                    $config->setDataByPath($path, $value);
                    $config->save();
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && 0 !== strpos($property->getDeclaringClass()->getName(), 'PHPUnit')) {
                $property->setAccessible(true);
                $property->setValue($this, null);
            }
        }
    }
}
