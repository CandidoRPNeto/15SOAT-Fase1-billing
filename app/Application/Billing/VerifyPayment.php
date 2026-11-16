<?php

namespace App\Application\Billing;

use App\Application\Ports\PaymentGateway;
use App\Domain\Billing\BillingMessage;
use App\Domain\Billing\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Polls the provider for one pending payment ("registro e verificação de
 * pagamentos"). Confirms only when the paid amount covers the charge.
 *
 * Example: $verify->verify($payment); // true when it became approved
 */
class VerifyPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly BillingEventEmitter $events,
    ) {}

    public function verify(Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::APPROVED) {
            return false;
        }

        $approved = $this->gateway->findApprovedPayment($payment->id);
        $payment->update(['last_checked_at' => now()]);

        if ($approved === null) {
            return false;
        }

        if ($approved->amount + 0.001 < (float) $payment->amount) {
            Log::error('billing.payment_amount_mismatch', ['payment_id' => $payment->id, 'expected' => $payment->amount, 'paid' => $approved->amount]);

            return false;
        }

        DB::transaction(fn () => $this->confirm($payment, $approved));

        return true;
    }

    /** @return int payments confirmed in this run */
    public function verifyPending(): int
    {
        return Payment::where('status', PaymentStatus::PENDING)->get()
            ->filter(fn (Payment $payment) => $this->verify($payment))
            ->count();
    }

    private function confirm(Payment $payment, ApprovedPayment $approved): void
    {
        $payment->update([
            'status' => PaymentStatus::APPROVED,
            'provider_payment_id' => $approved->providerPaymentId,
            'confirmed_at' => now(),
        ]);
        $this->events->emit(BillingMessage::PAYMENT_CONFIRMED, $payment->saga_id, $payment->correlation_id, [
            'service_order_id' => $payment->service_order_id,
            'payment_id' => $payment->id,
            'provider_payment_id' => $approved->providerPaymentId,
            'amount' => $approved->amount,
        ]);
    }
}
