<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiusAccountingSession extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime', 'updated_at_radius' => 'immutable_datetime',
            'stopped_at' => 'immutable_datetime', 'session_seconds' => 'integer',
            'input_octets' => 'integer', 'output_octets' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
