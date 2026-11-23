<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('automation_enabled')->default(false);
            $table->unsignedTinyInteger('grace_period_days')->default(3);
            $table->json('reminder_days')->nullable();
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('suspension_reason', 32)->nullable();
            $table->timestampTz('suspended_at')->nullable();
        });
        Schema::create('messaging_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('environment', 16)->default('sandbox');
            $table->string('username', 120);
            $table->text('api_key');
            $table->string('sender_id', 32)->nullable();
            $table->boolean('active')->default(false);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->string('channel', 16)->default('sms');
            $table->string('body', 500);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'event_key', 'channel']);
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('event_key', 64);
            $table->string('channel', 16)->default('sms');
            $table->text('destination');
            $table->string('idempotency_key', 191);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_reference', 120)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('next_attempt_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->index(['tenant_id', 'status', 'next_attempt_at']);
        });
        Schema::create('automated_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('action', 24);
            $table->string('idempotency_key', 191);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_access_events');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('messaging_accounts');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['suspension_reason', 'suspended_at']));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['automation_enabled', 'grace_period_days', 'reminder_days']));
    }
};
