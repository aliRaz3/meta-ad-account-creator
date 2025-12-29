<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Max Ad Accounts Per Job
    |--------------------------------------------------------------------------
    |
    | The maximum number of ad accounts that can be created in a single job.
    |
    */
    'max_ad_accounts_per_job' => env('MAX_AD_ACCOUNTS_PER_JOB', 5000),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for retrying failed ad account creation attempts.
    |
    */
    'retry_attempts' => env('RETRY_ATTEMPTS', 3),
    'retry_delay' => env('RETRY_DELAY', 0), // seconds

    /*
    |--------------------------------------------------------------------------
    | Meta API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Meta Graph API.
    |
    */
    'meta_api_version' => env('META_API_VERSION', 'v24.0'),
    'meta_api_base_url' => 'https://graph.facebook.com',

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of supported currencies for ad account creation.
    |
    */
    'currencies' => [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar',
        'JPY' => 'Japanese Yen',
        'CHF' => 'Swiss Franc',
        'CNY' => 'Chinese Yuan',
        'INR' => 'Indian Rupee',
        'BRL' => 'Brazilian Real',
        'MXN' => 'Mexican Peso',
        'SGD' => 'Singapore Dollar',
        'HKD' => 'Hong Kong Dollar',
        'NZD' => 'New Zealand Dollar',
        'SEK' => 'Swedish Krona',
        'NOK' => 'Norwegian Krone',
        'DKK' => 'Danish Krone',
        'PLN' => 'Polish Zloty',
        'THB' => 'Thai Baht',
        'TRY' => 'Turkish Lira',
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Ad Account Timezones
    |--------------------------------------------------------------------------
    |
    | Supported timezones for Meta ad account creation with their timezone IDs.
    |
    */
    'timezones' => [
        1 => [
            'label' => 'Pacific Time (PT)',
            'offset' => 'UTC-08:00',
            'region' => 'North America',
        ],
        2 => [
            'label' => 'Mountain Time (MT)',
            'offset' => 'UTC-07:00',
            'region' => 'North America',
        ],
        3 => [
            'label' => 'Central Time (CT)',
            'offset' => 'UTC-06:00',
            'region' => 'North America',
        ],
        4 => [
            'label' => 'Eastern Time (ET)',
            'offset' => 'UTC-05:00',
            'region' => 'North America',
        ],
        8 => [
            'label' => 'Greenwich Mean Time (GMT)',
            'offset' => 'UTC+00:00',
            'region' => 'Europe',
        ],
        47 => [
            'label' => 'Central European Time (CET)',
            'offset' => 'UTC+01:00',
            'region' => 'Europe',
        ],
        48 => [
            'label' => 'Eastern European Time (EET)',
            'offset' => 'UTC+02:00',
            'region' => 'Europe',
        ],
        57 => [
            'label' => 'China Standard Time (CST)',
            'offset' => 'UTC+08:00',
            'region' => 'Asia',
        ],
        58 => [
            'label' => 'Japan Standard Time (JST)',
            'offset' => 'UTC+09:00',
            'region' => 'Asia',
        ],
        59 => [
            'label' => 'Australian Eastern Time (AET)',
            'offset' => 'UTC+10:00',
            'region' => 'Australia',
        ],
        60 => [
            'label' => 'New Zealand Time (NZST)',
            'offset' => 'UTC+12:00',
            'region' => 'New Zealand',
        ],
        32 => [
            'label' => 'Brazil Time (BRT)',
            'offset' => 'UTC-03:00',
            'region' => 'South America',
        ],
        33 => [
            'label' => 'Argentina Time (ART)',
            'offset' => 'UTC-03:00',
            'region' => 'South America',
        ],
        45 => [
            'label' => 'India Standard Time (IST)',
            'offset' => 'UTC+05:30',
            'region' => 'Asia',
        ],
        46 => [
            'label' => 'Singapore Time (SGT)',
            'offset' => 'UTC+08:00',
            'region' => 'Asia',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Timezone
    |--------------------------------------------------------------------------
    |
    | Default timezone ID to use for new ad accounts.
    |
    */
    'default_timezone' => env('DEFAULT_TIMEZONE', 1), // Pacific Time

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | How often to refresh data in seconds (for Filament table polling).
    |
    */
    'polling_interval' => env('POLLING_INTERVAL', 5),
];
