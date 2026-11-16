# CLAUDE.md

Billing Service — Fase 4 microservice of Workshop OS (Laravel 13, PHP 8.4, PostgreSQL).

## Scope

- Owns budgets (approval/rejection) and payments (Mercado Pago Checkout Pro, polling verification).
- Never reads another service's database. OS data arrives in saga commands (`cmd.billing.*`).
- Architecture decisions live in `15SOAT-Fase1/docs/architecture` (RFC-004, ADR-010..015).

## Rules

- Every new function gets a test; every route gets `#[OA\*]` + a feature test.
- Routes sit behind Traefik ForwardAuth; identity comes from `X-User-*` (`TrustGatewayIdentity`).
- Payment provider only through `App\Application\Ports\PaymentGateway`; tests use `Tests\Fakes\FakePaymentGateway`.
- Messaging lib is synced from `15SOAT-Fase1/scripts/sync-messaging-lib.sh` — edit it there, not here.
- Never commit a Mercado Pago token.

## Commands

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan test                    # SQLite :memory:, PAYMENT_GATEWAY=offline
RABBITMQ_TEST_HOST=127.0.0.1 php artisan test   # + broker integration tests
php artisan bus:work                # outbox relay + cmd.billing.* consumer
php artisan payments:verify         # poll Mercado Pago once
./vendor/bin/pint
```
