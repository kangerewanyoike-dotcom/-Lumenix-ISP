<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active')->index();
            $table->string('timezone')->default('Africa/Nairobi');
            $table->char('currency', 3)->default('KES');
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'role', 'status']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT tenant_memberships_role_check CHECK (role IN ('owner', 'admin', 'support', 'agent'))");
        }

        Schema::create('platform_admins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestampsTz();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_memberships') && DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_memberships DROP CONSTRAINT tenant_memberships_role_check');
        }
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenants');
    }
};
