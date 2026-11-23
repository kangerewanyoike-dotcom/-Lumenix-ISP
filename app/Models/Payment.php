<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['daraja_account_id', 'customer_id', 'invoice_id', 'payment_request_id', 'receipt', 'account_reference', 'phone', 'amount_minor', 'currency', 'status', 'paid_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'paid_at' => 'immutable_datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
