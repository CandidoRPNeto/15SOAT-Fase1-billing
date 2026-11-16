<?php

namespace Tests\Feature;

use App\Application\Messaging\InboxProcessor;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GatewayHeaders;
use Tests\TestCase;

/** PAYMENT_GATEWAY=offline (phpunit.xml): full checkout → approve → verify without Mercado Pago. */
class OfflinePaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_checkout_can_be_approved_and_verified(): void
    {
        app(InboxProcessor::class)->process(MessageEnvelope::create('cmd.billing.request_payment', 's', 'c', [
            'service_order_id' => 3, 'service_order_number' => 'OS-3', 'client_id' => 10, 'amount' => 50.0,
        ]));
        $payment = Payment::sole();
        $this->assertSame('offline', $payment->provider);
        $this->assertStringEndsWith("/api/v1/payments/offline/{$payment->id}/approve", $payment->checkout_url);

        $this->withHeaders(GatewayHeaders::client(10))->getJson("/api/v1/payments/offline/{$payment->id}/approve")->assertOk();
        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/payments/{$payment->id}/verify")->assertJsonPath('confirmed', true);
    }

    public function test_unknown_offline_reference_is_404(): void
    {
        $this->withHeaders(GatewayHeaders::client(10))->getJson('/api/v1/payments/offline/nope/approve')->assertNotFound();
    }
}
