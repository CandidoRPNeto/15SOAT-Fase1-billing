<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\DecideBudget;
use App\Domain\Billing\BudgetDecisionException;
use App\Http\GatewayIdentity;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Budgets', description: 'Orçamentos gerados pela saga após o diagnóstico')]
class BudgetController extends Controller
{
    public function __construct(private readonly DecideBudget $decide) {}

    #[OA\Get(
        path: '/api/v1/budgets',
        summary: 'Lista orçamentos (cliente vê só os seus)',
        security: [['gateway' => []]],
        tags: ['Budgets'],
        parameters: [new OA\Parameter(name: 'service_order_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Orçamentos'), new OA\Response(response: 401, description: 'Sem identidade do gateway')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $identity = GatewayIdentity::fromRequest($request);
        $query = Budget::with('lines')->latest();

        if ($identity->isClient()) {
            $query->where('client_id', $identity->clientId);
        }
        if ($request->filled('service_order_id')) {
            $query->where('service_order_id', $request->integer('service_order_id'));
        }

        return BudgetResource::collection($query->get());
    }

    #[OA\Get(
        path: '/api/v1/budgets/{id}',
        summary: 'Exibe orçamento com linhas',
        security: [['gateway' => []]],
        tags: ['Budgets'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Orçamento'), new OA\Response(response: 403, description: 'Orçamento de outro cliente'), new OA\Response(response: 404, description: 'Não encontrado')]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->visibleBudget($request, $id, fn (Budget $budget) => response()->json(new BudgetResource($budget)));
    }

    #[OA\Post(
        path: '/api/v1/budgets/{id}/approve',
        summary: 'Cliente aprova o orçamento (dispara evt.billing.budget_approved para a saga)',
        security: [['gateway' => []]],
        tags: ['Budgets'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Aprovado'), new OA\Response(response: 422, description: 'Orçamento não está pendente')]
    )]
    public function approve(Request $request, string $id): JsonResponse
    {
        return $this->visibleBudget($request, $id, fn (Budget $budget) => $this->respondDecision(fn () => $this->decide->approve($budget)));
    }

    #[OA\Post(
        path: '/api/v1/budgets/{id}/reject',
        summary: 'Cliente rejeita o orçamento (a saga cancela a OS)',
        security: [['gateway' => []]],
        tags: ['Budgets'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Rejeitado'), new OA\Response(response: 422, description: 'Orçamento não está pendente')]
    )]
    public function reject(Request $request, string $id): JsonResponse
    {
        return $this->visibleBudget($request, $id, fn (Budget $budget) => $this->respondDecision(fn () => $this->decide->reject($budget)));
    }

    /** @param callable(Budget): JsonResponse $then */
    private function visibleBudget(Request $request, string $id, callable $then): JsonResponse
    {
        $budget = Budget::with('lines')->findOrFail($id);

        if (! GatewayIdentity::fromRequest($request)->canSee($budget->client_id)) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        return $then($budget);
    }

    /** @param callable(): Budget $decision */
    private function respondDecision(callable $decision): JsonResponse
    {
        try {
            return response()->json(new BudgetResource($decision()));
        } catch (BudgetDecisionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
