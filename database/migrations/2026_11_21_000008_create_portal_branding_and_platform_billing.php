<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('brand_name', 160)->nullable();
            $table->char('brand_primary_color', 7)->default('#4338CA');
            $table->boolean('powered_by_enabled')->default(true);
        });
        Schema::create('customer_portal_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('email', 254);
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'customer_id']);
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->cascadeOnDelete();
        });

        Schema::create('platform_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_per_subscriber_minor')->default(0);
            $table->char('currency', 3)->default('KES');
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('platform_usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('active_subscriber_count');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['tenant_id', 'period_start', 'period_end']);
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('platform_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('platform_subscription_id');
            $table->unsignedBigInteger('usage_snapshot_id');
            $table->string('number', 80);
            $table->string('status', 20)->default('issued');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('issued_at');
            $table->date('due_at');
            $table->char('currency', 3)->default('KES');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('balance_minor');
            $table->string('idempotency_key', 191);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'platform_subscription_id'])->references(['tenant_id', 'id'])->on('platform_subscriptions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'usage_snapshot_id'])->references(['tenant_id', 'id'])->on('platform_usage_snapshots')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('platform_usage_snapshots');
        Schema::dropIfExists('platform_subscriptions');
        Schema::dropIfExists('customer_portal_accounts');
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['brand_name', 'brand_primary_color', 'powered_by_enabled']));
    }
};
