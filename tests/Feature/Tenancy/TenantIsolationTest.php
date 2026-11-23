<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ScopedRecord;

beforeEach(function (): void {
    Schema::create('scoped_records', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('label');
        $table->timestamps();
    });
});

it('limits tenant scoped model queries and writes to the current tenant', function (): void {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
    $context = app(TenantContext::class);

    $context->set($tenantA);
    $recordA = ScopedRecord::create(['label' => 'A private record']);
    $context->set($tenantB);
    $recordB = ScopedRecord::create(['label' => 'B private record']);

    $context->set($tenantA);
    expect(ScopedRecord::query()->pluck('label')->all())->toBe(['A private record'])
        ->and(ScopedRecord::find($recordB->id))->toBeNull();

    $context->set($tenantB);
    expect(ScopedRecord::query()->pluck('label')->all())->toBe(['B private record']);
});

it('fails closed when a tenant scoped model is queried without context', function (): void {
    Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);

    expect(fn () => ScopedRecord::query()->count())->toThrow(LogicException::class, 'Tenant context is required.');
});

it('rejects a tenant selection that is not the signed in users membership', function (): void {
    $tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
    $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);
    $user = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'role' => 'agent', 'status' => 'active']);

    $this->actingAs($user)->post(route('tenant.select.store'), ['tenant_id' => $tenantB->id])->assertForbidden();
});

it('allows privileged tenant members into their workspace without an authenticator challenge', function (): void {
    $tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);
    $user = User::factory()->create(['email' => 'owner@example.test']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);

    $this->actingAs($user)->get(route('tenant.select'))->assertRedirect(route('tenant.dashboard'));
    $this->get(route('tenant.dashboard'))->assertOk();
});

it('lets a platform admin create and suspend tenants without an authenticator challenge', function (): void {
    $admin = User::factory()->create();
    PlatformAdmin::create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('platform.tenants.store'), ['name' => 'New ISP', 'slug' => 'new-isp'])
        ->assertRedirect();

    $tenant = Tenant::where('slug', 'new-isp')->firstOrFail();
    expect(AuditLog::where('action', 'tenant.created')->where('subject_id', (string) $tenant->id)->exists())->toBeTrue();

    $this->patch(route('platform.tenants.suspend', $tenant))->assertRedirect();

    expect($tenant->fresh()->status)->toBe('suspended')
        ->and(AuditLog::where('action', 'tenant.suspended')->where('subject_id', (string) $tenant->id)->exists())->toBeTrue();
});
