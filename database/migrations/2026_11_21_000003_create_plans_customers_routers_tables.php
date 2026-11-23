<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('KES');
            $table->unsignedInteger('speed_down_kbps');
            $table->unsignedInteger('speed_up_kbps');
            $table->string('billing_period', 20);
            $table->unsignedBigInteger('data_cap_bytes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'active']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('account_number', 64);
            $table->string('name', 160);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('pppoe_username', 64)->unique();
            $table->text('pppoe_password');
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->softDeletesTz();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'account_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'plan_id']);
            $table->foreign(['tenant_id', 'plan_id'], 'customers_tenant_plan_fk')
                ->references(['tenant_id', 'id'])->on('plans')->restrictOnDelete();
        });

        Schema::create('routers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('connection_mode', 20);
            $table->string('endpoint', 64)->nullable();
            $table->string('agent_id', 120)->nullable();
            $table->text('api_username')->nullable();
            $table->text('api_password')->nullable();
            $table->unsignedSmallInteger('api_port')->default(8729);
            $table->boolean('use_tls')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'agent_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('plans');
    }
};
