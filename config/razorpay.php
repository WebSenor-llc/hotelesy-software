<?php

return [
    'key_id'     => env('RAZORPAY_KEY_ID', ''),
    'key_secret' => env('RAZORPAY_KEY_SECRET', ''),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),

    // ID of a Razorpay subscription "plan" object created in their dashboard
    // representing the ₹999/month recurring charge.
    'monthly_plan_id' => env('RAZORPAY_MONTHLY_PLAN_ID', 'plan_test_monthly_999'),

    // The trial verification charge — ₹1 (in paise) authorizes the UPI
    // mandate so subsequent ₹999/mo charges go through.
    'mandate_amount_paise' => env('RAZORPAY_MANDATE_AMOUNT', 100),

    // Default monthly retail price for the Growth plan (in paise).
    'default_monthly_paise' => env('RAZORPAY_MONTHLY_PRICE', 99900),

    // Currency
    'currency' => 'INR',

    // After this many days the trial converts to paid.
    'trial_days' => env('RAZORPAY_TRIAL_DAYS', 30),

    // Toggle test mode → makes the integration fall back to a stub
    // when no key is configured (so dev environment works without keys).
    'test_mode' => env('RAZORPAY_TEST_MODE', true),
];
