<?php

namespace App\Application\Billing;

use App\Domain\Billing\BillingMessage;
use App\Domain\Billing\BudgetStatus;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\Budget;

/**
 * Handles cmd.billing.create_budget. Idempotent by service_order_id: a
 * re-sent command replies with the existing budget instead of duplicating it.
 */
class CreateBudget
{
    public function __construct(private readonly BillingEventEmitter $events) {}

    public function handle(MessageEnvelope $command): void
    {
        $existing = Budget::where('service_order_id', $command->payloadValue('service_order_id'))
            ->where('status', '!=', BudgetStatus::VOID)->first();

        if ($existing !== null) {
            $this->replyCreated($command, $existing);

            return;
        }

        $problem = $this->validationProblem($command);
        if ($problem !== null) {
            $this->events->emit(BillingMessage::BUDGET_CREATION_FAILED, $command->sagaId, $command->correlationId, [
                'service_order_id' => $command->payloadValue('service_order_id'),
                'reason' => $problem,
            ]);

            return;
        }

        $this->replyCreated($command, $this->store($command));
    }

    private function validationProblem(MessageEnvelope $command): ?string
    {
        $lines = $command->payloadValue('lines');

        if (! is_array($lines) || $lines === []) {
            return 'orçamento sem serviços ou itens';
        }

        return (float) $command->payloadValue('total') > 0 ? null : 'total do orçamento deve ser maior que zero';
    }

    private function store(MessageEnvelope $command): Budget
    {
        $budget = Budget::create([
            'service_order_id' => $command->payloadValue('service_order_id'),
            'service_order_number' => $command->payloadValue('service_order_number'),
            'client_id' => $command->payloadValue('client_id'),
            'status' => BudgetStatus::PENDING_APPROVAL,
            'total' => $command->payloadValue('total'),
            'diagnosis_notes' => $command->payload['diagnosis_notes'] ?? null,
            'saga_id' => $command->sagaId,
            'correlation_id' => $command->correlationId,
        ]);
        $budget->lines()->createMany($command->payloadValue('lines'));

        return $budget;
    }

    private function replyCreated(MessageEnvelope $command, Budget $budget): void
    {
        $this->events->emit(BillingMessage::BUDGET_CREATED, $command->sagaId, $command->correlationId, [
            'service_order_id' => $budget->service_order_id,
            'budget_id' => $budget->id,
            'total' => (float) $budget->total,
        ]);
    }
}
