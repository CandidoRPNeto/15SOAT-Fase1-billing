<?php

/*
 * RabbitMQ settings (ADR-011 in 15SOAT-Fase1). Billing consumes
 * cmd.billing.* on billing.inbox and publishes evt.billing.*.
 */
return [
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq.platform.svc.cluster.local'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'workshop'),
        'password' => env('RABBITMQ_PASSWORD', 'workshop'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'workshop'),
        'dead_letter_exchange' => env('RABBITMQ_DLX', 'workshop.dlx'),
        'queue' => env('RABBITMQ_QUEUE', 'billing.inbox'),
        'prefetch' => (int) env('RABBITMQ_PREFETCH', 10),
    ],

    'relay_batch_size' => (int) env('OUTBOX_RELAY_BATCH', 50),
];
