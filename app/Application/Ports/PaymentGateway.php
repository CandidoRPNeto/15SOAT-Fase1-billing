<?php

namespace App\Application\Ports;

use App\Application\Billing\ApprovedPayment;
use App\Application\Billing\CheckoutRequest;
use App\Application\Billing\CheckoutSession;

/**
 * Payment provider boundary (Mercado Pago in production, fakes in tests).
 */
interface PaymentGateway
{
    public function name(): string;

    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** Looks up an approved payment by our reference; null while unpaid. */
    public function findApprovedPayment(string $externalReference): ?ApprovedPayment;
}
