<?php

/**
 * Desktop edition config.
 * The cloud and desktop builds share one codebase. Set APP_MODE in .env:
 *   APP_MODE=cloud   → SaaS marketing landing, multi-tenant, Razorpay subscription
 *   APP_MODE=desktop → single-tenant offline build, license activation, no SaaS layer
 */

return [
    /*
     * Mode flag — drives conditional service-provider registration,
     * route filtering, and middleware loading.
     */
    'mode' => env('APP_MODE', 'cloud'),   // cloud | desktop

    /*
     * The desktop build is hard-bound to a single tenant + property.
     * The Hotelesy installer creates them on first run.
     */
    'tenant_id'   => env('DESKTOP_TENANT_ID', 1),
    'property_id' => env('DESKTOP_PROPERTY_ID', 1),

    /*
     * License-server URL — the cloud Hotelesy instance you run that issues
     * activation codes and validates phone-home pings.
     */
    'license_server_url' => env('DESKTOP_LICENSE_SERVER', 'https://hotelesy.com/api/desktop'),

    /*
     * Phone-home cadence + offline grace.
     */
    'phone_home_every_days'  => env('DESKTOP_PHONE_HOME_DAYS', 7),
    'soft_warn_after_days'   => env('DESKTOP_SOFT_WARN_DAYS', 30),    // ≥30d offline → banner
    'lock_readonly_after_days' => env('DESKTOP_LOCK_READONLY_DAYS', 60),// ≥60d offline → read-only

    /*
     * Local license file location (encrypted JSON).
     * Stored in storage/app/desktop/ so it survives across releases.
     */
    'license_file' => storage_path('app/desktop/license.json'),

    /*
     * Build & branding for the desktop app shell.
     */
    'window_title'  => 'Hotelesy',
    'window_width'  => 1440,
    'window_height' => 900,
    'min_width'     => 1180,
    'min_height'    => 720,

    /*
     * Optional online sync (when desktop user has internet).
     * If true, periodically pushes a redacted snapshot to the cloud account
     * the activation key was issued under. Lets a hotelier switch between
     * cloud and offline editions without losing data.
     */
    'cloud_sync_enabled' => env('DESKTOP_CLOUD_SYNC', false),
];
