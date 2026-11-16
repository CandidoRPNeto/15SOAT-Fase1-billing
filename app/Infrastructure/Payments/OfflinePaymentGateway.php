<?php

namespace App\Infrastructure\Payments;

use App\Application\Billing\ApprovedPayment;
use App\Application\Billing\CheckoutRequest;
use App\Application\Billing\CheckoutSession;
use App\Application\Ports\PaymentGateway;
use Illuminate\Support\Facades\Cache;

/**
 * Local fallback when no Mercado Pago test token is configured
 * (PAYMENT_GATEWAY=offline). The checkout URL is our own
 * /api/v1/payments/offline/{reference}/approve, which marks it paid.
 */
class OfflinePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly string $baseUrl) {}

    public function name(): string
    {
        return 'offline';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        Cache::forever($this->key($request->externalReference), $request->amount);

        return new CheckoutSession(
            'offline-'.$request->externalReference,
            rtrim($this->baseUrl, '/')."/api/v1/payments/offline/{$request->externalReference}/approve",
        );
    }

    public function findApprovedPayment(string $externalReference): ?ApprovedPayment
    {
        $amount = Cache::get($this->key($externalReference).':approved');

        return $amount === null ? null : new ApprovedPayment('offline-'.$externalReference, (float) $amount);
    }

    /** Simulates the buyer paying at the provider. */
    public function approve(string $externalReference): bool
    {
        $amount = Cache::get($this->key($externalReference));

        if ($amount === null) {
            return false;
        }

        Cache::forever($this->key($externalReference).':approved', $amount);

        return true;
    }

    private function key(string $reference): string
    {
        return "offline-payment:{$reference}";
    }
}
