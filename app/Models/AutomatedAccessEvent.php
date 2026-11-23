<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AutomatedAccessEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'invoice_id', 'action', 'idempotency_key', 'status', 'attempts', 'error_code', 'completed_at'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'completed_at' => 'immutable_datetime'];
    }
}
