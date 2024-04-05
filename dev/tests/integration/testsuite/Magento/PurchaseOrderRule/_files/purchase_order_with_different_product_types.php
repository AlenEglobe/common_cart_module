<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Create a company with purchase orders and a single rule, single role and single approver
 */

use Magento\Company\Api\AclInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterfaceFactory;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Model\PurchaseOrderQuoteConverter;
use Magento\PurchaseOrderRule\Api\AppliedRuleRepositoryInterface;
use Magento\PurchaseOrderRule\Api\RuleRepositoryInterface;
use Magento\PurchaseOrderRule\Model\AppliedRuleFactory;
use Magento\PurchaseOrderRule\Model\RuleFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Eav\Model\Config;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Registry;
use Magento\Customer\Api\CustomerRepositoryInterface;

Resolver::getInstance()->requireDataFixture('Magento/ConfigurableProduct/_files/product_configurable.php');
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure.php');

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $objectManager->get(CustomerInterfaceFactory::class);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);

/** @var QuoteRepository $quoteRepository */
$quoteRepository = $objectManager->get(QuoteRepository::class);

/** @var PurchaseOrderInterfaceFactory $purchaseOrderFactory */
$purchaseOrderFactory = $objectManager->get(PurchaseOrderInterfaceFactory::class);

/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

/** @var PurchaseOrderQuoteConverter $purchaseOrderQuoteConverter */
$purchaseOrderQuoteConverter = $objectManager->get(PurchaseOrderQuoteConverter::class);

/** @var JsonSerializer $jsonSerializer */
$jsonSerializer = $objectManager->get(JsonSerializer::class);

/** @var AclInterface $companyAcl */
$companyAcl = $objectManager->get(AclInterface::class);

/** @var RuleRepositoryInterface $ruleRepository */
$ruleRepository = $objectManager->get(RuleRepositoryInterface::class);

/** @var RuleFactory $ruleFactory */
$ruleFactory = $objectManager->get(RuleFactory::class);

/** @var AppliedRuleFactory $appliedRuleFactory */
$appliedRuleFactory = $objectManager->get(AppliedRuleFactory::class);

/** @var AppliedRuleRepositoryInterface $appliedRuleRepository */
$appliedRuleRepository = $objectManager->get(AppliedRuleRepositoryInterface::class);

/** @var DataObjectHelper $dataObjectHelper */
$dataObjectHelper = $objectManager->get(DataObjectHelper::class);

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);

$buyerCustomer = $customerFactory->create();

$eavConfig = Bootstrap::getObjectManager()->get(Config::class);
$attribute = $eavConfig->getAttribute(Product::ENTITY, 'test_configurable');

$company = $registry->registry('company');

$dataObjectHelper->populateWithArray(
    $buyerCustomer,
    [
        'firstname' => 'Buyer',
        'lastname' => 'Buyer',
        'email' => 'buyer@example.com',
        'website_id' => 1,
        'extension_attributes' => [
            'company_attributes' => [
                'company_id' => $company->getId(),
                'status' => 1,
                'job_title' => 'Sales Rep'
            ]
        ]
    ],
    CustomerInterface::class
);
$customerRepository->save($buyerCustomer, 'password');
$buyerCustomer = $customerRepository->get('buyer@example.com');

$purchaseOrdersData = [
    [
        'company_id' => $company->getId(),
        'creator_id' => $buyerCustomer->getId(),
        'status' => PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED,
        'grand_total' => 20,
        'auto_approve' => 0,
        'is_validate' => 0
    ]
];

$productsData = [
    [
        'id' => 301,
        'type' => Type::TYPE_SIMPLE,
        'name' => 'Simple Product',
        'sku' => 'simple_product_po_rule'

    ],
    [
        'id' => 302,
        'type' => Type::TYPE_VIRTUAL,
        'name' => 'Virtual Product',
        'sku' => 'virtual_product_po_rule'
    ]
];

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

foreach ($productsData as $productData) {
    /** @var $product ProductInterface */
    $product = $objectManager->create(ProductInterface::class);
    $product->setTypeId($productData['type'])
        ->setId($productData['id'])
        ->setName($productData['name'])
        ->setSku($productData['sku'])
        ->setWebsiteIds([1])
        ->setPrice(20)
        ->setVisibility(Visibility::VISIBILITY_BOTH)
        ->setStatus(Status::STATUS_ENABLED)
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 22, 'is_in_stock' => 1])
        ->setAttributeSetId(4)
        ->setIsSalable(true)
        ->setSalable(true);

    $productRepository->save($product);
}

$simpleProduct = $productRepository->get('simple_product_po_rule');
$virtualProduct = $productRepository->get('virtual_product_po_rule');
$configurableProduct = $productRepository->get('configurable');

foreach ($purchaseOrdersData as $purchaseOrderData) {
    // Create a new quote for the customer
    /** @var Quote $quote */
    $quote = $quoteFactory->create();
    $quote->setStoreId(1)
        ->setIsActive(true)
        ->setCustomerId($purchaseOrderData['creator_id'])
        ->setIsMultiShipping(false)
        ->setReservedOrderId('reserved_order_id');
    $quote->addProduct($simpleProduct->load($simpleProduct->getId()), 2);
    $quote->addProduct($virtualProduct->load($virtualProduct->getId()), 3);
    $quote->addProduct(
        $configurableProduct->load($configurableProduct->getId()),
        new DataObject(
            [
                'product' => 1,
                'selected_configurable_option' => 1,
                'qty' => 4,
                'super_attribute' => [
                    $attribute->getId() => $attribute->getOptions()[1]->getValue()
                ]
            ]
        )
    );
    $quote->addProduct(
        $configurableProduct->load($configurableProduct->getId()),
        new DataObject(
            [
                'product' => 1,
                'selected_configurable_option' => 2,
                'qty' => 5,
                'super_attribute' => [
                    $attribute->getId() => $attribute->getOptions()[2]->getValue()
                ]
            ]
        )
    );
    $quote->getPayment()->setMethod('checkmo');
    $quote->collectTotals();
    $quoteRepository->save($quote);

    // Update the quote information on the purchase order
    $purchaseOrderData['quote_id'] = $quote->getId();
    $purchaseOrderData['snapshot_quote'] = $quote;
    $purchaseOrderData['payment_method'] = 'checkmo';

    // Create a new purchase order for the customer
    /** @var PurchaseOrderInterface $purchaseOrder */
    $purchaseOrder = $purchaseOrderFactory->create();

    $dataObjectHelper->populateWithArray(
        $purchaseOrder,
        $purchaseOrderData,
        PurchaseOrderInterface::class
    );

    $purchaseOrderRepository->save($purchaseOrder);
}
