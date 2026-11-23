<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->unsignedSmallInteger('vat_rate_basis_points')->default(0);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->date('billing_anchor_date')->nullable();
            $table->unique(['tenant_id', 'id'], 'customers_tenant_id_unique');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('number', 80);
            $table->string('status', 24)->default('issued');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issued_at');
            $table->date('due_at');
            $table->char('currency', 3)->default('KES');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_minor');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('balance_minor');
            $table->string('idempotency_key', 191);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'id'], 'invoices_tenant_id_unique');
            $table->unique(['tenant_id', 'customer_id', 'id']);
            $table->index(['tenant_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'customer_id', 'issued_at']);
            $table->foreign(['tenant_id', 'customer_id'], 'invoices_tenant_customer_fk')
                ->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->string('description', 240);
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedSmallInteger('tax_rate_basis_points');
            $table->unsignedBigInteger('tax_minor');
            $table->unsignedBigInteger('total_minor');
            $table->string('source_type', 80)->nullable();
            $table->string('source_id', 80)->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'invoice_id'], 'invoice_lines_tenant_invoice_fk')
                ->references(['tenant_id', 'id'])->on('invoices')->cascadeOnDelete();
            $table->index(['tenant_id', 'invoice_id']);
        });

        Schema::create('credit_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->string('kind', 24);
            $table->string('description', 240);
            $table->string('idempotency_key', 191);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
            $table->foreign(['tenant_id', 'customer_id'], 'credits_tenant_customer_fk')
                ->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'], 'credits_tenant_invoice_fk')
                ->references(['tenant_id', 'id'])->on('invoices')->restrictOnDelete();
        });

        Schema::create('billing_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('operation', 32);
            $table->date('effective_at');
            $table->string('idempotency_key', 191);
            $table->json('result')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'customer_id'], 'billing_operations_tenant_customer_fk')
                ->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_operations');
        Schema::dropIfExists('credit_ledger_entries');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_tenant_id_unique');
            $table->dropColumn('billing_anchor_date');
        });
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('vat_rate_basis_points'));
    }
};
