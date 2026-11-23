<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daraja_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('environment', 16)->default('sandbox');
            $table->string('short_code', 32);
            $table->string('account_type', 16)->default('paybill');
            $table->text('consumer_key');
            $table->text('consumer_secret');
            $table->text('passkey')->nullable();
            $table->text('callback_token');
            $table->char('callback_token_hash', 64)->unique();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['environment', 'short_code', 'active']);
        });

        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('daraja_account_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('idempotency_key', 191);
            $table->string('merchant_request_id', 100)->nullable();
            $table->string('checkout_request_id', 100)->nullable()->unique();
            $table->string('phone', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 24)->default('pending');
            $table->string('provider_result_code', 24)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'daraja_account_id'])->references(['tenant_id', 'id'])->on('daraja_accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('daraja_account_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('payment_request_id')->nullable();
            $table->string('receipt', 64);
            $table->string('account_reference', 64)->nullable();
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->string('status', 24)->default('unmatched');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'receipt']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'daraja_account_id'])->references(['tenant_id', 'id'])->on('daraja_accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'payment_request_id'])->references(['tenant_id', 'id'])->on('payment_requests')->restrictOnDelete();
            $table->index(['tenant_id', 'status', 'paid_at']);
        });

        Schema::create('payment_callback_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 191);
            $table->char('payload_hash', 64);
            $table->string('event_type', 24);
            $table->string('result', 24);
            $table->timestampTz('created_at');
            $table->unique(['tenant_id', 'event_key']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('allocated_by');
            $table->timestampTz('created_at');
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_callback_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('daraja_accounts');
    }
};
