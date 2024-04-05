<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

return [
    [
        'requestPermissions' => [
            'Magento_Company::index' => 'allow',
            'Magento_NegotiableQuote::all' => 'allow',
            'Magento_NegotiableQuote::view_quotes' => 'allow',
            'Magento_NegotiableQuote::manage' => 'allow',
            'Magento_NegotiableQuote::checkout' => 'allow',
            'Magento_NegotiableQuote::view_quotes_sub' => 'allow',
            'Magento_Company::credit' => 'allow',
            'Magento_Company::credit_history' => 'allow',
        ],
        'responsePermissions' => [
            'Magento_Company::index' => 'allow',
            'Magento_Sales::all' => 'deny',
            'Magento_Sales::place_order' => 'deny',
            'Magento_Sales::payment_account' => 'deny',
            'Magento_Sales::view_orders' => 'deny',
            'Magento_Sales::view_orders_sub' => 'deny',
            'Magento_NegotiableQuote::all' => 'allow',
            'Magento_NegotiableQuote::view_quotes' => 'allow',
            'Magento_NegotiableQuote::manage' => 'allow',
            'Magento_NegotiableQuote::checkout' => 'allow',
            'Magento_NegotiableQuote::view_quotes_sub' => 'allow',
            'Magento_PurchaseOrder::all' => 'deny',
            'Magento_PurchaseOrder::view_purchase_orders' => 'deny',
            'Magento_PurchaseOrder::view_purchase_orders_for_subordinates' => 'deny',
            'Magento_PurchaseOrder::view_purchase_orders_for_company' => 'deny',
            'Magento_PurchaseOrder::autoapprove_purchase_order' => 'deny',
            'Magento_PurchaseOrderRule::super_approve_purchase_order' => 'deny',
            'Magento_PurchaseOrderRule::view_approval_rules' => 'deny',
            'Magento_PurchaseOrderRule::manage_approval_rules' => 'deny',
            'Magento_Company::view' => 'deny',
            'Magento_Company::view_account' => 'deny',
            'Magento_Company::edit_account' => 'deny',
            'Magento_Company::view_address' => 'deny',
            'Magento_Company::edit_address' => 'deny',
            'Magento_Company::contacts' => 'deny',
            'Magento_Company::payment_information' => 'deny',
            'Magento_Company::shipping_information' => 'deny',
            'Magento_Company::user_management' => 'deny',
            'Magento_Company::roles_view' => 'deny',
            'Magento_Company::roles_edit' => 'deny',
            'Magento_Company::users_view' => 'deny',
            'Magento_Company::users_edit' => 'deny',
            'Magento_Company::credit' => 'allow',
            'Magento_Company::credit_history' => 'allow',
        ],
    ],
];
