<?php

namespace Tests\Feature;

use App\Application\Billing\CheckoutRequest;
use App\Infrastructure\Payments\MercadoPagoPaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoPaymentGatewayTest extends TestCase
{
    private function gateway(bool $sandbox = true): MercadoPagoPaymentGateway
    {
        return new MercadoPagoPaymentGateway(['access_token' => 'TEST-token', 'base_url' => 'https://api.mercadopago.com', 'use_sandbox_url' => $sandbox]);
    }

    public function test_creates_checkout_preference_with_reference_and_brl_item(): void
    {
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => 'pref-1', 'init_point' => 'https://mp/init', 'sandbox_init_point' => 'https://mp/sandbox',
        ], 201)]);

        $session = $this->gateway()->createCheckout(new CheckoutRequest('pay-1', 'OS-1', 230.456));

        $this->assertSame('pref-1', $session->preferenceId);
        $this->assertSame('https://mp/sandbox', $session->checkoutUrl);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer TEST-token')
            && $r['external_reference'] === 'pay-1'
            && $r['items'][0]['currency_id'] === 'BRL'
            && $r['items'][0]['unit_price'] === 230.46);
    }

    public function test_uses_production_init_point_when_sandbox_disabled(): void
    {
        Http::fake(['*' => Http::response(['id' => 'p', 'init_point' => 'https://mp/init', 'sandbox_init_point' => 'https://mp/sandbox'])]);

        $this->assertSame('https://mp/init', $this->gateway(sandbox: false)->createCheckout(new CheckoutRequest('r', 't', 1))->checkoutUrl);
    }

    public function test_finds_approved_payment_by_external_reference(): void
    {
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [
            ['id' => 11, 'status' => 'rejected', 'transaction_amount' => 230],
            ['id' => 12, 'status' => 'approved', 'transaction_amount' => 230],
        ]])]);

        $approved = $this->gateway()->findApprovedPayment('pay-1');

        $this->assertSame('12', $approved?->providerPaymentId);
        $this->assertSame(230.0, $approved?->amount);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'external_reference=pay-1'));
    }

    public function test_returns_null_while_unpaid(): void
    {
        Http::fake(['*' => Http::response(['results' => []])]);

        $this->assertNull($this->gateway()->findApprovedPayment('pay-1'));
        $this->assertSame('mercadopago', $this->gateway()->name());
    }

    public function test_provider_error_throws(): void
    {
        Http::fake(['*' => Http::response(['message' => 'invalid token'], 401)]);

        $this->expectException(RequestException::class);
        $this->gateway()->createCheckout(new CheckoutRequest('r', 't', 1));
    }
}
