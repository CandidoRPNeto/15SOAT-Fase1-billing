<?php

namespace App\Application\Billing;

final readonly class ApprovedPayment
{
    public function __construct(
        public string $providerPaymentId,
        public float $amount,
    ) {}
}
