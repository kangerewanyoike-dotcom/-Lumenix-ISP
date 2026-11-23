<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedBigInteger('monthly_price_minor')->default(0);
            $table->char('currency', 3)->default('KES');
            $table->unsignedInteger('included_subscribers')->default(0);
            $table->unsignedInteger('max_routers')->default(1);
            $table->jsonb('features')->default('[]');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('platform_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_invoice_id')->nullable()->constrained('platform_invoices')->nullOnDelete();
            $table->string('provider', 32)->default('manual');
            $table->string('reference', 120)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('confirmed');
            $table->timestampTz('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['tenant_id', 'paid_at']);
        });
        Schema::create('platform_support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 180);
            $table->text('description');
            $table->string('priority', 16)->default('normal');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'priority', 'created_at']);
        });
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestampsTz();
        });
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestampTz('approved_at')->nullable();
        });
        DB::table('tenants')->where('status', 'active')->update(['approved_at' => now()]);
        Schema::table('platform_subscriptions', function (Blueprint $table): void {
            $table->foreignId('platform_plan_id')->nullable()->after('tenant_id')->constrained('platform_plans')->nullOnDelete();
        });

        foreach ([
            ['Free', 0, 100, 2, ['Core billing', 'Customer management']],
            ['Basic', 0, 500, 5, ['Core billing', 'Customer portal', 'Email reminders']],
            ['Pro', 0, 5000, 25, ['All Basic features', 'Automation', 'Advanced reports']],
            ['Enterprise', 0, 1000000, 1000, ['All Pro features', 'Priority support', 'Custom integrations']],
        ] as [$name, $price, $subscribers, $routers, $features]) {
            DB::table('platform_plans')->insert([
                'name' => $name, 'monthly_price_minor' => $price, 'currency' => 'KES',
                'included_subscribers' => $subscribers, 'max_routers' => $routers,
                'features' => json_encode($features), 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('platform_subscriptions', fn (Blueprint $table) => $table->dropConstrainedForeignId('platform_plan_id'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('approved_at'));
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('platform_support_tickets');
        Schema::dropIfExists('platform_payments');
        Schema::dropIfExists('platform_plans');
    }
};
