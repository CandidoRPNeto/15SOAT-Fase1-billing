<?php

namespace App\Application\Billing;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $externalReference,
        public string $title,
        public float $amount,
    ) {}
}
