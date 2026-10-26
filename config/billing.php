<?php

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'demo_mode' => filter_var(env('BILLING_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN)
        && in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
    'grace_days' => env('BILLING_GRACE_DAYS') !== null
        ? (int) env('BILLING_GRACE_DAYS')
        : (filter_var(env('BILLING_DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN)
            && in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) ? 7 : null),
    'subscription_cycle_count' => [
        'monthly' => env('RAZORPAY_MONTHLY_CYCLE_COUNT') === null ? null : (int) env('RAZORPAY_MONTHLY_CYCLE_COUNT'),
        'yearly' => env('RAZORPAY_YEARLY_CYCLE_COUNT') === null ? null : (int) env('RAZORPAY_YEARLY_CYCLE_COUNT'),
    ],
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'api_url' => env('RAZORPAY_API_URL', 'https://api.razorpay.com/v1'),
    ],
    'seller' => [
        'name' => env('BILLING_SELLER_NAME'),
        'gstin' => env('BILLING_SELLER_GSTIN'),
        'pan' => env('BILLING_SELLER_PAN'),
        'address' => env('BILLING_SELLER_ADDRESS'),
        'state_code' => env('BILLING_SELLER_STATE_CODE'),
        'gst_rate' => env('BILLING_GST_RATE') === null ? null : (float) env('BILLING_GST_RATE'),
        'sac_code' => env('BILLING_SAC_CODE'),
        'price_tax_mode' => env('BILLING_PRICE_TAX_MODE'),
    ],
];
