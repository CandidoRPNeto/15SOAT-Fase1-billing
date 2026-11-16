<?php

namespace App\Application\Billing;

use App\Application\Messaging\OutboxRecorder;
use App\Domain\Billing\BillingMessage;
use App\Domain\Messaging\MessageEnvelope;
use Illuminate\Support\Facades\Log;

/**
 * Emits evt.billing.* through the outbox, echoing the saga id the OS
 * orchestrator expects.
 *
 * Example: $emitter->emit(BillingMessage::BUDGET_CREATED, $sagaId, $correlationId, ['budget_id' => $id]);
 */
class BillingEventEmitter
{
    public function __construct(private readonly OutboxRecorder $outbox) {}

    /** @param array<string, mixed> $payload */
    public function emit(BillingMessage $type, string $sagaId, string $correlationId, array $payload): void
    {
        $this->outbox->record(MessageEnvelope::create($type->value, $sagaId, $correlationId, $payload));
        Log::info('billing.event', ['type' => $type->value, 'saga_id' => $sagaId, 'payload' => $payload]);
    }
}
