<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformUsageSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'period_start', 'period_end', 'active_subscriber_count', 'unit_price_minor', 'amount_minor', 'created_at'];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'active_subscriber_count' => 'integer', 'unit_price_minor' => 'integer', 'amount_minor' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
