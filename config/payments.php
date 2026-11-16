<?php

/*
 * Payment provider. `mercadopago` needs a TEST access token (never commit
 * it; it lives in the K8s Secret). `offline` is the local fallback.
 */
return [
    'gateway' => env('PAYMENT_GATEWAY', 'mercadopago'),

    'mercadopago' => [
        'access_token' => (string) env('MERCADOPAGO_ACCESS_TOKEN', ''),
        'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
        // TEST credentials pay through sandbox_init_point.
        'use_sandbox_url' => (bool) env('MERCADOPAGO_USE_SANDBOX_URL', true),
    ],

    'offline' => [
        'base_url' => env('PUBLIC_BASE_URL', 'http://localhost:30080'),
    ],
];
