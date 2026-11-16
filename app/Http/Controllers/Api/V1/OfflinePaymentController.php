<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Ports\PaymentGateway;
use App\Infrastructure\Payments\OfflinePaymentGateway;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class OfflinePaymentController extends Controller
{
    #[OA\Get(
        path: '/api/v1/payments/offline/{reference}/approve',
        summary: 'Somente PAYMENT_GATEWAY=offline: simula o comprador pagando no provedor',
        security: [['gateway' => []]],
        tags: ['Payments'],
        parameters: [new OA\Parameter(name: 'reference', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Marcado como pago no provedor simulado'), new OA\Response(response: 404, description: 'Gateway não é offline ou referência desconhecida')]
    )]
    public function approve(string $reference, PaymentGateway $gateway): JsonResponse
    {
        if (! $gateway instanceof OfflinePaymentGateway || ! $gateway->approve($reference)) {
            return response()->json(['message' => 'Checkout offline não encontrado.'], 404);
        }

        return response()->json(['message' => 'Pagamento simulado aprovado; aguarde a verificação.']);
    }
}
