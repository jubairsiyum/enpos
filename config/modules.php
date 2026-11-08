<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Core Modules (always available to every shop)
    |--------------------------------------------------------------------------
    */
    'core' => [
        'home',
        'user_management',
        'contacts',
        'products',
        'purchases',
        'sell',
        'pos',
        'stock_transfers',
        'stock_adjustment',
        'expenses',
        'payment_accounts',
        'reports',
        'notification_templates',
        'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Modules (plan-gated, then toggled per shop)
    |--------------------------------------------------------------------------
    */
    'optional' => [
        'hrm',
        'essentials',
        'cheque',
        'woocommerce',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module metadata for UI display
    |--------------------------------------------------------------------------
    */
    'meta' => [
        'home'                   => ['label' => 'Home',                   'icon' => 'HomeIcon'],
        'user_management'        => ['label' => 'User Management',        'icon' => 'UsersIcon'],
        'contacts'               => ['label' => 'Contacts',               'icon' => 'ContactsIcon'],
        'products'               => ['label' => 'Products',               'icon' => 'CubeIcon'],
        'purchases'              => ['label' => 'Purchases',              'icon' => 'ShoppingCartIcon'],
        'sell'                   => ['label' => 'Sell',                   'icon' => 'CashIcon'],
        'pos'                    => ['label' => 'POS',                    'icon' => 'DesktopComputerIcon'],
        'stock_transfers'        => ['label' => 'Stock Transfers',        'icon' => 'SwitchHorizontalIcon'],
        'stock_adjustment'       => ['label' => 'Stock Adjustment',       'icon' => 'AdjustmentsIcon'],
        'expenses'               => ['label' => 'Expenses',               'icon' => 'CreditCardIcon'],
        'payment_accounts'       => ['label' => 'Payment Accounts',      'icon' => 'BanknotesIcon'],
        'reports'                => ['label' => 'Reports',                'icon' => 'ChartBarIcon'],
        'notification_templates' => ['label' => 'Notification Templates','icon' => 'BellIcon'],
        'settings'               => ['label' => 'Settings',              'icon' => 'CogIcon'],
        'hrm'                    => ['label' => 'HRM',                    'icon' => 'OfficeBuildingIcon'],
        'essentials'             => ['label' => 'Essentials',            'icon' => 'StarIcon'],
        'cheque'                 => ['label' => 'Cheque',                'icon' => 'DocumentTextIcon'],
        'woocommerce'            => ['label' => 'Woocommerce',           'icon' => 'ShoppingBagIcon'],
    ],
];

