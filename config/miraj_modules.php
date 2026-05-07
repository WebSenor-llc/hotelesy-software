<?php

/**
 * Miraj Hotel Suite — module catalog with build status.
 *
 * status: "ready" | "scaffolded" | "stub" | "planned"
 *  - ready      → migrations + controllers + views all wired
 *  - scaffolded → migrations + controllers, no views/UI
 *  - stub       → routes/migrations only, no logic
 *  - planned    → not yet started
 */
return [
    'core' => [
        'label' => 'Core (always on)',
        'items' => [
            ['code' => 'pms',      'name' => 'Property Management',  'status' => 'scaffolded', 'desc' => 'Reservations, check-in/out, folios, multi-property'],
            ['code' => 'users',    'name' => 'User & Role Management','status' => 'scaffolded', 'desc' => '14 roles, granular permissions'],
            ['code' => 'reports',  'name' => 'Standard Reports',     'status' => 'planned',    'desc' => 'Occupancy, ARR, RevPAR, daily flash'],
        ],
    ],
    'operations' => [
        'label' => 'Operations',
        'items' => [
            ['code' => 'pos',         'name' => 'Point of Sale',           'status' => 'scaffolded', 'desc' => 'Restaurant/bar/room-service orders'],
            ['code' => 'kds',         'name' => 'Kitchen Display System',  'status' => 'scaffolded', 'desc' => 'Cook tickets, prep timing analytics'],
            ['code' => 'banquet',     'name' => 'Banquet & Sales',         'status' => 'scaffolded', 'desc' => 'Hall booking, BEO printing'],
            ['code' => 'housekeeping','name' => 'Housekeeping',            'status' => 'scaffolded', 'desc' => 'Room status, lost & found'],
            ['code' => 'store',       'name' => 'Store / Materials',       'status' => 'scaffolded', 'desc' => 'Inventory, vendors, POs'],
            ['code' => 'maintenance', 'name' => 'Maintenance Engineering', 'status' => 'planned',    'desc' => 'Tickets, preventive schedules'],
            ['code' => 'spa',         'name' => 'Spa & Wellness',          'status' => 'planned',    'desc' => 'Therapist roster, treatments'],
        ],
    ],
    'distribution' => [
        'label' => 'Distribution',
        'items' => [
            ['code' => 'channel_manager', 'name' => 'Channel Manager',         'status' => 'scaffolded', 'desc' => 'OTA sync via AxisRooms, STAAH'],
            ['code' => 'booking_engine',  'name' => 'Direct Booking Engine',   'status' => 'planned',    'desc' => 'On-property booking widget'],
            ['code' => 'cms',             'name' => 'Hotel Website (CMS)',     'status' => 'scaffolded', 'desc' => 'Multi-page hotel site'],
            ['code' => 'crs',             'name' => 'Central Reservation System','status' => 'planned', 'desc' => 'Multi-property unified inventory'],
        ],
    ],
    'finance' => [
        'label' => 'Finance',
        'items' => [
            ['code' => 'accounts',    'name' => 'Financial Management', 'status' => 'scaffolded', 'desc' => 'COA, GST returns, bank rec'],
            ['code' => 'payroll',     'name' => 'Payroll & HR',         'status' => 'planned',    'desc' => 'Staff, attendance, payroll'],
            ['code' => 'fb_costing',  'name' => 'F&B Cost Control',     'status' => 'planned',    'desc' => 'Recipe + plate costing'],
        ],
    ],
    'crm' => [
        'label' => 'Marketing / CRM',
        'items' => [
            ['code' => 'reviews',   'name' => 'Review Management', 'status' => 'scaffolded', 'desc' => 'Aggregated reviews inbox'],
            ['code' => 'revenue',   'name' => 'Revenue Management','status' => 'scaffolded', 'desc' => 'Rate shopper, dynamic pricing'],
            ['code' => 'crm',       'name' => 'Guest CRM & Loyalty','status' => 'planned',   'desc' => 'Profiles, segmentation, tiers'],
            ['code' => 'amenities', 'name' => 'Amenities / Add-ons','status' => 'scaffolded','desc' => 'Sell pickups, breakfast, spa'],
            ['code' => 'whatsapp',  'name' => 'WhatsApp Engagement','status' => 'planned',   'desc' => 'AiSensy/Gallabox integration'],
            ['code' => 'membership','name' => 'Membership / Club',  'status' => 'planned',   'desc' => 'Member cards, prepaid wallets'],
        ],
    ],
    'integrations' => [
        'label' => 'Integrations',
        'items' => [
            ['code' => 'door_locks',     'name' => 'Door Locks',     'status' => 'planned', 'desc' => 'Onity, Saflok, dormakaba, Salto'],
            ['code' => 'id_scanner',     'name' => 'ID Scanner / OCR','status' => 'planned', 'desc' => 'Aadhaar, passport, voter ID'],
            ['code' => 'payment_gateway','name' => 'Payment Gateway', 'status' => 'planned', 'desc' => 'Razorpay, Stripe, PayU, CCAvenue'],
        ],
    ],
];
