<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\VerifyPayment;
use App\Http\GatewayIdentity;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Payments', description: 'Pagamentos via Mercado Pago (Checkout Pro, verificação por polling)')]
class PaymentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/payments',
        summary: 'Lista pagamentos (cliente vê só os seus); inclui checkout_url do Mercado Pago',
        security: [['gateway' => []]],
        tags: ['Payments'],
        parameters: [new OA\Parameter(name: 'service_order_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Pagamentos')]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $identity = GatewayIdentity::fromRequest($request);
        $query = Payment::query()->latest();

        if ($identity->isClient()) {
            $query->where('client_id', $identity->clientId);
        }
        if ($request->filled('service_order_id')) {
            $query->where('service_order_id', $request->integer('service_order_id'));
        }

        return PaymentResource::collection($query->get());
    }

    #[OA\Get(
        path: '/api/v1/payments/{id}',
        summary: 'Exibe pagamento',
        security: [['gateway' => []]],
        tags: ['Payments'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Pagamento'), new OA\Response(response: 403, description: 'De outro cliente')]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        if (! GatewayIdentity::fromRequest($request)->canSee($payment->client_id)) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        return response()->json(new PaymentResource($payment));
    }

    #[OA\Post(
        path: '/api/v1/payments/{id}/verify',
        summary: 'Consulta o Mercado Pago agora (o job payments:verify faz o mesmo a cada minuto)',
        security: [['gateway' => []]],
        tags: ['Payments'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Status após a verificação; confirmed=true quando aprovou agora')]
    )]
    public function verify(Request $request, string $id, VerifyPayment $verify): JsonResponse
    {
        $payment = Payment::findOrFail($id);

        if (! GatewayIdentity::fromRequest($request)->canSee($payment->client_id)) {
            return response()->json(['message' => 'Acesso não autorizado.'], 403);
        }

        $confirmed = $verify->verify($payment);

        return response()->json(['confirmed' => $confirmed, 'payment' => new PaymentResource($payment->refresh())]);
    }
}
