<?php

namespace App\Application\Billing;

final readonly class CheckoutSession
{
    public function __construct(
        public string $preferenceId,
        public string $checkoutUrl,
    ) {}
}
