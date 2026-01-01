<?php

return [
	'baseUrl'       => env( 'IYZIPAY_BASE_URL', '' ),
	'apiKey'        => env( 'IYZIPAY_API_KEY', '' ),
	'secretKey'     => env( 'IYZIPAY_SECRET_KEY', '' ),
	'billableModel' => 'App\User',
    'plans' => [
        'gold-monthly' => [
            'name'       => 'Gold Membership',
            'price'      => 100,             // Price in base currency unit (e.g., 100 TRY)
            'currency'   => 'TRY',
            'interval'   => 'monthly',       // 'monthly' or 'yearly'
            'trialDays'  => 7,
            'features'   => ['access_all', 'no_ads'], // Optional custom attributes
        ],

        // Example: Yearly Pro Plan
        // 'pro-yearly' => [
        //     'name'       => 'Pro Membership',
        //     'price'      => 1000,
        //     'currency'   => 'TRY',
        //     'interval'   => 'yearly',
        //     'trialDays'  => 0,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Load Migrations
    |--------------------------------------------------------------------------
    |
    | If you want to customize the migrations (e.g., use ULID or UUID),
    | set this to false, publish the migrations, and edit them in your
    | database/migrations folder.
    |
    */
    'load_migrations' => true,
];
