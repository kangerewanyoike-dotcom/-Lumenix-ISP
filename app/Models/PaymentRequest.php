<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['daraja_account_id', 'customer_id', 'invoice_id', 'idempotency_key', 'merchant_request_id', 'checkout_request_id', 'phone', 'amount_minor', 'status', 'provider_result_code', 'completed_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'completed_at' => 'immutable_datetime'];
    }
}
