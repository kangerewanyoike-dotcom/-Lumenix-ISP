<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSubscription extends Model
{
    protected $fillable = ['tenant_id', 'platform_plan_id', 'price_per_subscriber_minor', 'currency', 'billing_day', 'status'];

    protected function casts(): array
    {
        return ['price_per_subscriber_minor' => 'integer', 'billing_day' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'platform_plan_id');
    }
}
