<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use BelongsToTenant;

    protected $fillable = ['customer_id', 'invoice_id', 'event_key', 'channel', 'destination', 'idempotency_key', 'status', 'attempts', 'provider_reference', 'error_code', 'next_attempt_at', 'sent_at'];

    protected $hidden = ['destination'];

    protected function casts(): array
    {
        return ['destination' => 'encrypted', 'attempts' => 'integer', 'next_attempt_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }
}
