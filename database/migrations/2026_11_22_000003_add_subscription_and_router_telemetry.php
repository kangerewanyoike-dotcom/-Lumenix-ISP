<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('subscription_status', 24)->default('active');
            $table->string('renewal_mode', 16)->default('manual');
            $table->date('current_period_ends_at')->nullable();
            $table->string('provisioning_status', 24)->default('pending');
            $table->timestampTz('provisioned_at')->nullable();
        });
        DB::table('customers')->where('status', 'suspended')->update(['subscription_status' => 'suspended']);
        DB::table('customers')->whereIn('status', ['suspended', 'disconnected'])->update(['provisioning_status' => 'blocked']);
        DB::table('customers')->where('status', 'active')->update(['provisioning_status' => 'ready']);

        Schema::table('routers', function (Blueprint $table): void {
            $table->decimal('cpu_percent', 5, 2)->nullable();
            $table->decimal('memory_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->unsignedBigInteger('download_bps')->nullable();
            $table->unsignedBigInteger('upload_bps')->nullable();
            $table->timestampTz('telemetry_updated_at')->nullable();
        });

        Schema::table('radius_accounting_sessions', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->change();
            $table->string('username', 64)->nullable();
            $table->string('access_type', 16)->default('pppoe');
        });
        DB::table('radius_accounting_sessions')->update(['access_type' => 'pppoe']);

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'receipt']);
            $table->unique(['tenant_id', 'daraja_account_id', 'receipt'], 'payments_tenant_account_receipt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_tenant_account_receipt_unique');
            $table->unique(['tenant_id', 'receipt']);
        });
        Schema::table('radius_accounting_sessions', function (Blueprint $table): void {
            $table->dropColumn(['username', 'access_type']);
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
        });
        Schema::table('routers', fn (Blueprint $table) => $table->dropColumn(['cpu_percent', 'memory_percent', 'uptime_seconds', 'download_bps', 'upload_bps', 'telemetry_updated_at']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['subscription_status', 'renewal_mode', 'current_period_ends_at', 'provisioning_status', 'provisioned_at']));
    }
};
