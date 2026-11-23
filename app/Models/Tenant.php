<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'country_code', 'status', 'timezone', 'currency', 'suspended_at', 'approved_at', 'vat_rate_basis_points', 'automation_enabled', 'grace_period_days', 'reminder_days', 'brand_name', 'brand_primary_color', 'powered_by_enabled'];

    protected function casts(): array
    {
        return ['suspended_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'vat_rate_basis_points' => 'integer', 'automation_enabled' => 'boolean', 'grace_period_days' => 'integer', 'reminder_days' => 'array', 'powered_by_enabled' => 'boolean'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function routers(): HasMany
    {
        return $this->hasMany(Router::class);
    }

    public function platformSubscription(): HasOne
    {
        return $this->hasOne(PlatformSubscription::class);
    }

    public function platformSupportTickets(): HasMany
    {
        return $this->hasMany(PlatformSupportTicket::class);
    }
}
