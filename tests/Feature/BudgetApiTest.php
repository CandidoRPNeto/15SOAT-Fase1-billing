<?php

namespace Tests\Feature;

use App\Domain\Billing\BudgetStatus;
use App\Models\Budget;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GatewayHeaders;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_gateway_identity_are_rejected(): void
    {
        $this->getJson('/api/v1/budgets')->assertUnauthorized();
        $this->withHeaders(['X-User-Id' => '1', 'X-User-Role' => 'admin'])->getJson('/api/v1/budgets')->assertUnauthorized();
    }

    public function test_client_lists_only_own_budgets_and_staff_filters_by_order(): void
    {
        Budget::factory()->create(['client_id' => 10, 'service_order_id' => 1]);
        Budget::factory()->create(['client_id' => 11, 'service_order_id' => 2]);

        $this->withHeaders(GatewayHeaders::client(10))->getJson('/api/v1/budgets')->assertOk()->assertJsonCount(1, 'data');
        $this->withHeaders(GatewayHeaders::staff())->getJson('/api/v1/budgets?service_order_id=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.client_id', 11);
    }

    public function test_show_hides_other_clients_budget_and_404s_unknown(): void
    {
        $budget = Budget::factory()->create(['client_id' => 11]);

        $this->withHeaders(GatewayHeaders::client(10))->getJson("/api/v1/budgets/{$budget->id}")->assertForbidden();
        $this->withHeaders(GatewayHeaders::staff())->getJson("/api/v1/budgets/{$budget->id}")->assertOk()->assertJsonPath('total', 230);
        $this->withHeaders(GatewayHeaders::staff())->getJson('/api/v1/budgets/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_client_approves_and_saga_event_is_recorded_with_the_budget_saga_id(): void
    {
        $budget = Budget::factory()->create(['client_id' => 10]);

        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/budgets/{$budget->id}/approve")
            ->assertOk()->assertJsonPath('status', 'approved');

        $event = OutboxMessage::sole()->body;
        $this->assertSame('evt.billing.budget_approved', $event['type']);
        $this->assertSame($budget->saga_id, $event['saga_id']);
    }

    public function test_client_rejects_budget(): void
    {
        $budget = Budget::factory()->create(['client_id' => 10]);

        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/budgets/{$budget->id}/reject")
            ->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertSame('evt.billing.budget_rejected', OutboxMessage::sole()->type);
    }

    public function test_cannot_decide_twice_or_decide_a_void_budget(): void
    {
        $budget = Budget::factory()->create(['client_id' => 10, 'status' => BudgetStatus::VOID]);

        $this->withHeaders(GatewayHeaders::client(10))->postJson("/api/v1/budgets/{$budget->id}/approve")
            ->assertUnprocessable()->assertJsonPath('message', "Budget {$budget->id} is void; expected pending_approval");
        $this->assertSame(0, OutboxMessage::count());
    }

    public function test_staff_cannot_approve_and_other_client_cannot_approve(): void
    {
        $budget = Budget::factory()->create(['client_id' => 10]);

        $this->withHeaders(GatewayHeaders::staff())->postJson("/api/v1/budgets/{$budget->id}/approve")->assertForbidden();
        $this->withHeaders(GatewayHeaders::client(99))->postJson("/api/v1/budgets/{$budget->id}/approve")->assertForbidden();
    }
}
