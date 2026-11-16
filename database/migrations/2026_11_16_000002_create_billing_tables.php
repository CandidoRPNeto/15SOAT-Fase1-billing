<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing owns budgets and payments (RFC-004). service_order_id/client_id
 * are references to the OS Service, never foreign keys to its database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('service_order_id')->index();
            $table->string('service_order_number', 20);
            $table->unsignedBigInteger('client_id')->index();
            $table->string('status', 20);
            $table->decimal('total', 10, 2);
            $table->text('diagnosis_notes')->nullable();
            $table->uuid('saga_id');
            $table->string('correlation_id', 64);
            $table->string('void_reason', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->unsignedBigInteger('reference_id');
            $table->string('description', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('service_order_id')->unique();
            $table->string('service_order_number', 20);
            $table->unsignedBigInteger('client_id')->index();
            $table->decimal('amount', 10, 2);
            $table->string('status', 20);
            $table->string('provider', 20);
            $table->string('preference_id', 120)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('provider_payment_id', 60)->nullable();
            $table->uuid('saga_id');
            $table->string('correlation_id', 64);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
