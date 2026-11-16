<?php

namespace Tests\Feature;

use App\Application\Billing\ApprovedPayment;
use App\Application\Messaging\InboxProcessor;
use App\Application\Ports\PaymentGateway;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\OutboxMessage;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Fakes\FakePaymentGateway;
use Tests\Support\GatewayHeaders;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function requestPayment(int $orderId = 7): void
    {
        app(InboxProcessor::class)->process(MessageEnvelope::create('cmd.billing.request_payment', 'saga-9', 'corr-9', [
            'service_order_id' => $orderId, 'service_order_number' => 'OS-2026-00007', 'client_id' => 10, 'amount' => 230.0,
        ]));
    }

    public function test_request_payment_opens_checkout_and_replies_with_its_url(): void
    {
        $this->requestPayment();

        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status->value);
        $this->assertSame("https://checkout.test/{$payment->id}", $payment->checkout_url);
        $this->assertSame($payment->id, $this->gateway->checkouts[0]->externalReference);
        $reply = OutboxMessage::sole()->body;
        $this->assertSame('evt.billing.payment_requested', $reply['type']);
        $this->assertSame($payment->checkout_url, $reply['payload']['checkout_url']);
    }

    public function test_resent_request_reuses_the_checkout(): void
    {
        $this->requestPayment();
        $this->requestPayment();

        $this->assertSame(1, Payment::count());
        $this->assertCount(1, $this->gateway->checkouts);
    }

    public function test_provider_outage_rolls_back_so_the_retry_can_succeed(): void
    {
        $this->gateway->down = true;
        try {
            $this->requestPayment();
            $this->fail('expected provider exception');
        } catch (RuntimeException) {
            $this->assertSame(0, Payment::count());
        }
    }

    public function test_scheduled_verification_confirms_paid_payment_and_emits_event(): void
    {
        $this->requestPayment();
        $payment = Payment::sole();
        $this->artisan('payments:verify')->expectsOutput('confirmed=0')->assertSuccessful();

        $this->gateway->approved[$payment->id] = new ApprovedPayment('mp-123', 230.0);
        $this->artisan('payments:verify')->expectsOutput('confirmed=1')->assertSuccessful();

        $this->assertSame('approved', $payment->refresh()->status->value);
        $this->assertSame('mp-123', $payment->provider_payment_id);
        $event = OutboxMessage::orderByDesc('id')->first()->body;
        $this->assertSame('evt.billing.payment_confirmed', $event['type']);
        $this->assertSame('saga-9', $event['saga_id']);
    }

    public function test_underpaid_amount_is_not_confirmed(): void
    {
        $payment = Payment::factory()->create(['amount' => 230.0]);
        $this->gateway->approved[$payment->id] = new ApprovedPayment('mp-1', 10.0);

        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/payments/{$payment->id}/verify")
            ->assertOk()->assertJsonPath('confirmed', false)->assertJsonPath('payment.status', 'pending');
    }

    public function test_verify_endpoint_confirms_once(): void
    {
        $payment = Payment::factory()->create();
        $this->gateway->approved[$payment->id] = new ApprovedPayment('mp-2', 230.0);

        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/payments/{$payment->id}/verify")
            ->assertOk()->assertJsonPath('confirmed', true)->assertJsonPath('payment.status', 'approved');
        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/payments/{$payment->id}/verify")
            ->assertJsonPath('confirmed', false);
        $this->assertSame(1, OutboxMessage::count());
    }

    public function test_payment_endpoints_enforce_ownership(): void
    {
        $payment = Payment::factory()->create(['client_id' => 11]);

        $this->withHeaders(GatewayHeaders::client(10))->getJson("/api/v1/payments/{$payment->id}")->assertForbidden();
        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/payments/{$payment->id}/verify")->assertForbidden();
        $this->withHeaders(GatewayHeaders::client(10))->getJson('/api/v1/payments')->assertOk()->assertJsonCount(0, 'data');
        $this->withHeaders(GatewayHeaders::staff())->getJson("/api/v1/payments?service_order_id={$payment->service_order_id}")->assertJsonCount(1, 'data');
        $this->withHeaders(GatewayHeaders::staff())->getJson("/api/v1/payments/{$payment->id}")->assertOk()->assertJsonPath('amount', 230);
    }
}
