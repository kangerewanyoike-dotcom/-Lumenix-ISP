<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'plan_id', 'account_number', 'name', 'phone', 'email', 'address', 'pppoe_username', 'pppoe_password', 'status', 'subscription_status', 'renewal_mode', 'current_period_ends_at', 'provisioning_status', 'provisioned_at', 'billing_day', 'billing_anchor_date', 'suspension_reason', 'suspended_at',
    ];

    protected $hidden = ['pppoe_password'];

    protected function casts(): array
    {
        return [
            'pppoe_password' => 'encrypted',
            'billing_day' => 'integer',
            'billing_anchor_date' => 'immutable_date',
            'suspended_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_date',
            'provisioned_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function radiusAccountingSessions(): HasMany
    {
        return $this->hasMany(RadiusAccountingSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function portalAccount(): HasOne
    {
        return $this->hasOne(CustomerPortalAccount::class);
    }
}
