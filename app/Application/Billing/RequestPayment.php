<?php

namespace App\Application\Billing;

use App\Application\Ports\PaymentGateway;
use App\Domain\Billing\BillingMessage;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\Payment;

/**
 * Handles cmd.billing.request_payment: opens a Mercado Pago checkout for the
 * finalized order. Idempotent by service_order_id. A provider error throws,
 * the message is dead-lettered and the OS watchdog re-sends the command.
 */
class RequestPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly BillingEventEmitter $events,
    ) {}

    public function handle(MessageEnvelope $command): void
    {
        $payment = Payment::firstWhere('service_order_id', $command->payloadValue('service_order_id'))
            ?? $this->open($command);

        $this->events->emit(BillingMessage::PAYMENT_REQUESTED, $command->sagaId, $command->correlationId, [
            'service_order_id' => $payment->service_order_id,
            'payment_id' => $payment->id,
            'checkout_url' => $payment->checkout_url,
            'provider' => $payment->provider,
        ]);
    }

    private function open(MessageEnvelope $command): Payment
    {
        $payment = Payment::create([
            'service_order_id' => $command->payloadValue('service_order_id'),
            'service_order_number' => $command->payloadValue('service_order_number'),
            'client_id' => $command->payloadValue('client_id'),
            'amount' => $command->payloadValue('amount'),
            'status' => PaymentStatus::PENDING,
            'provider' => $this->gateway->name(),
            'saga_id' => $command->sagaId,
            'correlation_id' => $command->correlationId,
        ]);

        $session = $this->gateway->createCheckout(new CheckoutRequest(
            $payment->id,
            "Oficina — OS {$payment->service_order_number}",
            (float) $payment->amount,
        ));
        $payment->update(['preference_id' => $session->preferenceId, 'checkout_url' => $session->checkoutUrl]);

        return $payment;
    }
}
