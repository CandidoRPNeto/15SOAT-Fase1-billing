<?php

namespace Tests\Feature;

use App\Application\Messaging\InboxProcessor;
use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\Budget;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BudgetCommandsTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $payload */
    private function send(string $type, array $payload): void
    {
        app(InboxProcessor::class)->process(MessageEnvelope::create($type, 'saga-1', 'corr-1', $payload));
    }

    /** @return array<string, mixed> */
    private function budgetPayload(array $overrides = []): array
    {
        return [
            'service_order_id' => 7, 'service_order_number' => 'OS-2026-00007', 'client_id' => 10, 'total' => 230.0,
            'diagnosis_notes' => 'pastilhas gastas',
            'lines' => [['kind' => 'item', 'reference_id' => 1, 'description' => 'Pastilha', 'quantity' => 2, 'unit_price' => 115.0]],
            ...$overrides,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function emitted(): array
    {
        return OutboxMessage::orderBy('id')->get()->map(fn (OutboxMessage $m) => $m->body)->all();
    }

    public function test_create_budget_stores_snapshot_and_replies_created_with_saga_id(): void
    {
        $this->send('cmd.billing.create_budget', $this->budgetPayload());

        $budget = Budget::with('lines')->sole();
        $this->assertSame('pending_approval', $budget->status->value);
        $this->assertCount(1, $budget->lines);
        $reply = $this->emitted()[0];
        $this->assertSame('evt.billing.budget_created', $reply['type']);
        $this->assertSame('saga-1', $reply['saga_id']);
        $this->assertSame($budget->id, $reply['payload']['budget_id']);
        $this->assertEquals(230.0, $reply['payload']['total']);
    }

    public function test_resent_create_budget_replies_with_the_same_budget(): void
    {
        $this->send('cmd.billing.create_budget', $this->budgetPayload());
        $this->send('cmd.billing.create_budget', $this->budgetPayload());

        $this->assertSame(1, Budget::count());
        $replies = $this->emitted();
        $this->assertSame($replies[0]['payload']['budget_id'], $replies[1]['payload']['budget_id']);
    }

    public function test_budget_without_lines_replies_creation_failed(): void
    {
        $this->send('cmd.billing.create_budget', $this->budgetPayload(['lines' => [], 'total' => 0]));

        $this->assertSame(0, Budget::count());
        $this->assertSame('evt.billing.budget_creation_failed', $this->emitted()[0]['type']);
        $this->assertSame('orçamento sem serviços ou itens', $this->emitted()[0]['payload']['reason']);
    }

    public function test_zero_total_replies_creation_failed(): void
    {
        $this->send('cmd.billing.create_budget', $this->budgetPayload(['total' => 0]));

        $this->assertSame('total do orçamento deve ser maior que zero', $this->emitted()[0]['payload']['reason']);
    }

    public function test_void_budget_compensates_and_is_idempotent_when_missing(): void
    {
        $this->send('cmd.billing.void_budget', ['service_order_id' => 999, 'reason' => 'nada a anular']);
        $this->send('cmd.billing.create_budget', $this->budgetPayload());
        $this->send('cmd.billing.void_budget', ['service_order_id' => 7, 'reason' => 'estoque insuficiente']);

        $budget = Budget::sole();
        $this->assertSame('void', $budget->status->value);
        $this->assertSame('estoque insuficiente', $budget->void_reason);
    }

    public function test_after_void_a_new_create_opens_a_fresh_budget(): void
    {
        $this->send('cmd.billing.create_budget', $this->budgetPayload());
        $this->send('cmd.billing.void_budget', ['service_order_id' => 7, 'reason' => 'x']);
        $this->send('cmd.billing.create_budget', $this->budgetPayload());

        $this->assertSame(2, Budget::count());
    }

    public function test_router_binds_to_billing_commands(): void
    {
        $this->assertSame(['cmd.billing.*'], app(MessageRouter::class)->bindings());
    }

    public function test_router_rejects_unknown_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported message type 'cmd.billing.unknown'");

        $this->send('cmd.billing.unknown', []);
    }
}
