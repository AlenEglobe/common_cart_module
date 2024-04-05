<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Controller\Delete;

use Magento\Company\Model\CompanyUser;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrderRule\Controller\Delete\Index as DeleteController;
use Magento\PurchaseOrderRule\Model\Rule;
use Magento\PurchaseOrderRule\Model\RuleRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Action\Context;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IndexTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var RequestInterface|HttpRequest|MockObject
     */
    private $request;

    /**
     * @var RedirectFactory|MockObject
     */
    private $resultRedirectFactory;

    /**
     * @var Redirect|MockObject
     */
    private $resultRedirect;

    /**
     * @var ManagerInterface|MockObject
     */
    private $messageManager;

    /**
     * @var RuleRepository|MockObject
     */
    private $ruleRepository;

    /**
     * @var CompanyUser
     */
    private $companyUser;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var DeleteController
     */
    private $controller;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = ObjectManager::getInstance();

        $this->request = $this->getMockForAbstractClass(RequestInterface::class);
        $this->resultRedirectFactory = $this->getMockBuilder(RedirectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->resultRedirect = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->messageManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->ruleRepository = $this->objectManager->get(RuleRepository::class);
        $this->companyUser = $this->objectManager->get(CompanyUser::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->session = $this->objectManager->get(Session::class);

        $context = $this->objectManager->create(
            Context::class,
            [
                'request' => $this->request,
                'messageManager' => $this->messageManager,
                'resultRedirectFactory' => $this->resultRedirectFactory
            ]
        );

        $this->controller = $this->objectManager->create(
            DeleteController::class,
            [
                'context' => $context
            ]
        );
    }

    /**
     * Test that the user is redirected with an error if no ID is passed.
     */
    public function testMissingRuleId()
    {
        $this->request->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('id')
            ->willReturn(null);

        $this->assertErrorMethodAndRedirect('The selected rule does not exist.');
    }

    /**
     * Test the rule ID being present but not existing
     */
    public function testInvalidRuleId()
    {
        $this->request->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('id')
            ->willReturn(999);

        $this->assertErrorMethodAndRedirect('The selected rule does not exist.');
    }

    /**
     * Delete a valid rule created from a data fixture
     *
     * @magentoDataFixture Magento/PurchaseOrderRule/_files/approval_rule.php
     * @magentoDbIsolation disabled
     */
    public function testDelete()
    {
        $companyAdmin = $this->customerRepository->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        $rules = $this->ruleRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('company_id', $this->companyUser->getCurrentCompanyId())
                ->addFilter('name', 'Integration Test Rule Name')
                ->create()
        );

        $this->assertEquals(1, $rules->getTotalCount());

        /* @var Rule $rule */
        $rule = current($rules->getItems());

        $this->request->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('id')
            ->willReturn($rule->getId());

        $this->messageManager->expects($this->once())
            ->method('addSuccessMessage')
            ->with(__('The rule has been deleted.'));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setPath')
            ->with('purchaseorderrule/');

        $this->controller->execute();

        // Verify the rule no longer exists in the database
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Rule with id "' . $rule->getId() . '" does not exist.');

        $this->ruleRepository->get($rule->getId());
    }

    /**
     * Error an error message is present and a redirect to the referral URL occurs
     *
     * @param $message
     */
    private function assertErrorMethodAndRedirect($message)
    {
        $this->messageManager->expects($this->once())
            ->method('addErrorMessage')
            ->with(__($message));

        $this->resultRedirectFactory->expects($this->once())
            ->method('create')
            ->willReturn($this->resultRedirect);

        $this->resultRedirect->expects($this->once())
            ->method('setRefererUrl');

        $this->controller->execute();
    }
}
