<?php

namespace App\Infrastructure\Payments;

use App\Application\Billing\ApprovedPayment;
use App\Application\Billing\CheckoutRequest;
use App\Application\Billing\CheckoutSession;
use App\Application\Ports\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Mercado Pago REST API (Checkout Pro preference + payment search).
 * Uses TEST credentials locally; confirmation is polled (no public webhook URL in kind).
 * Docs: https://www.mercadopago.com.br/developers/pt/reference
 */
class MercadoPagoPaymentGateway implements PaymentGateway
{
    /** @param array{access_token: string, base_url: string, use_sandbox_url: bool} $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'mercadopago';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $response = $this->client()->post('/checkout/preferences', [
            'external_reference' => $request->externalReference,
            'items' => [[
                'title' => $request->title,
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => round($request->amount, 2),
            ]],
        ])->throw()->json();

        $url = $this->config['use_sandbox_url'] && ! empty($response['sandbox_init_point'])
            ? $response['sandbox_init_point']
            : $response['init_point'];

        return new CheckoutSession((string) $response['id'], (string) $url);
    }

    public function findApprovedPayment(string $externalReference): ?ApprovedPayment
    {
        $results = $this->client()->get('/v1/payments/search', [
            'external_reference' => $externalReference,
            'sort' => 'date_created',
            'criteria' => 'desc',
        ])->throw()->json('results', []);

        foreach ($results as $payment) {
            if (($payment['status'] ?? null) === 'approved') {
                return new ApprovedPayment((string) $payment['id'], (float) $payment['transaction_amount']);
            }
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->withToken($this->config['access_token'])
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, throw: false);
    }
}
