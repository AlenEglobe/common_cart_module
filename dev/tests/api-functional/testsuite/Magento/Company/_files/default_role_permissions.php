<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

return [
    [
        'permissions' => [
            'Magento_Company::index' => 'allow',
            'Magento_Sales::all' => 'allow',
            'Magento_Sales::place_order' => 'allow',
            'Magento_Sales::payment_account' => 'deny',
            'Magento_Sales::view_orders' => 'allow',
            'Magento_Sales::view_orders_sub' => 'deny',
            'Magento_NegotiableQuote::all' => 'allow',
            'Magento_NegotiableQuote::view_quotes' => 'allow',
            'Magento_NegotiableQuote::manage' => 'allow',
            'Magento_NegotiableQuote::checkout' => 'allow',
            'Magento_NegotiableQuote::view_quotes_sub' => 'deny',
            'Magento_PurchaseOrder::all' => 'allow',
            'Magento_PurchaseOrder::view_purchase_orders' => 'allow',
            'Magento_PurchaseOrder::view_purchase_orders_for_subordinates' => 'allow',
            'Magento_PurchaseOrder::view_purchase_orders_for_company' => 'deny',
            'Magento_PurchaseOrder::autoapprove_purchase_order' => 'deny',
            'Magento_PurchaseOrderRule::super_approve_purchase_order' => 'deny',
            'Magento_PurchaseOrderRule::view_approval_rules' => 'allow',
            'Magento_PurchaseOrderRule::manage_approval_rules' => 'deny',
            'Magento_Company::view' => 'allow',
            'Magento_Company::view_account' => 'allow',
            'Magento_Company::edit_account' => 'deny',
            'Magento_Company::view_address' => 'allow',
            'Magento_Company::edit_address' => 'deny',
            'Magento_Company::contacts' => 'allow',
            'Magento_Company::payment_information' => 'allow',
            'Magento_Company::shipping_information' => 'allow',
            'Magento_Company::user_management' => 'allow',
            'Magento_Company::roles_view' => 'deny',
            'Magento_Company::roles_edit' => 'deny',
            'Magento_Company::users_view' => 'allow',
            'Magento_Company::users_edit' => 'deny',
            'Magento_Company::credit' => 'deny',
            'Magento_Company::credit_history' => 'deny',
        ],
    ],
];
