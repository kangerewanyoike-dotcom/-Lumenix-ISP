<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('plan_id');
            $table->foreignId('router_id')->nullable()->constrained('routers')->restrictOnDelete();
            $table->string('username', 64)->unique();
            $table->text('password');
            $table->string('status', 20)->default('active');
            $table->foreign(['tenant_id', 'plan_id'])->references(['tenant_id', 'id'])->on('plans')->restrictOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_vouchers');
    }
};
