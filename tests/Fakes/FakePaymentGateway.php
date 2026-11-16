<?php

namespace Tests\Fakes;

use App\Application\Billing\ApprovedPayment;
use App\Application\Billing\CheckoutRequest;
use App\Application\Billing\CheckoutSession;
use App\Application\Ports\PaymentGateway;
use RuntimeException;

/** Scriptable payment provider for tests. */
class FakePaymentGateway implements PaymentGateway
{
    /** @var list<CheckoutRequest> */
    public array $checkouts = [];

    /** @var array<string, ApprovedPayment> reference => payment */
    public array $approved = [];

    public bool $down = false;

    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        if ($this->down) {
            throw new RuntimeException("Provider unavailable for {$request->externalReference}");
        }
        $this->checkouts[] = $request;

        return new CheckoutSession('pref-'.count($this->checkouts), "https://checkout.test/{$request->externalReference}");
    }

    public function findApprovedPayment(string $externalReference): ?ApprovedPayment
    {
        return $this->approved[$externalReference] ?? null;
    }
}
