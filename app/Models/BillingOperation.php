<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingOperation extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['customer_id', 'operation', 'effective_at', 'idempotency_key', 'result', 'created_at'];

    protected function casts(): array
    {
        return ['effective_at' => 'immutable_date', 'result' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
