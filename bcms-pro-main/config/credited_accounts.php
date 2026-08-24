<?php

/**
 * Map credited account numbers (bankAccountNo) to ERMS coding details.
 *
 * IMPORTANT: keep credited account numbers as strings to preserve leading zeros.
 */
return [
    /**
     * credited_acc_num => ['account_code' => ..., 'activity_code' => ..., 'gfs_code' => ...]
     */
    '011103000689' => [
        'account_code' => '1010011010',
        'activity_code' => '0000000000',
        'gfs_code' => '32115109',
        'currency_code' => 'TZS',
    ],

    '11103000689' => [
        'account_code' => '1010011010',
        'activity_code' => '0000000000',
        'gfs_code' => '32115109',
        'currency_code' => 'TZS',
    ],

    '011139000653' => [
        'account_code' => '1010011130',
        'activity_code' => '0000000000',
        'gfs_code' => '32115109',
        'currency_code' => 'TZS',
    ],

    '01J1020758900' => [
        'account_code' => '1010021040',
        'activity_code' => '0000000000',
        'gfs_code' => '32113107',
        'currency_code' => 'TZS',
    ],

    '01J1028249400' => [
        'account_code' => '1010021050',
        'activity_code' => '0000000000',
        'gfs_code' => '32113107',
        'currency_code' => 'TZS',
    ],

    '0250028249400' => [
        'account_code' => '1010021370',
        'activity_code' => '0000000000',
        'gfs_code' => '32113107',
        'currency_code' => 'USD',
    ],

    '20110084040' => [
        'account_code' => '1010031310',
        'activity_code' => '0000000000',
        'gfs_code' => '32114108',
        'currency_code' => 'TZS',
    ],

    '9925264454' => [
        'account_code' => '1010170010',
        'activity_code' => '0000000000',
        'gfs_code' => '32121101',
        'currency_code' => 'TZS',
    ],

    '0400106034' => [
        'account_code' => '1010062010',
        'activity_code' => '0000000000',
        'gfs_code' => '32111133',
        'currency_code' => 'TZS',
    ],

    '20110040025' => [
        'account_code' => '1010031300',
        'activity_code' => '0000000000',
        'gfs_code' => '32114108',
        'currency_code' => 'TZS',
    ],

    '0108006003900' => [
        'account_code' => '1010041010',
        'activity_code' => '0000000000',
        'gfs_code' => '32111125',
        'currency_code' => 'TZS',
    ],

    '8708006003901' => [
        'account_code' => '1010041050',
        'activity_code' => '0000000000',
        'gfs_code' => '32111124',
        'currency_code' => 'USD',
    ],

    '001000208509' => [
        'account_code' => '1010051040',
        'activity_code' => '0000000000',
        'gfs_code' => '32119113',
        'currency_code' => 'TZS',
    ],
];

