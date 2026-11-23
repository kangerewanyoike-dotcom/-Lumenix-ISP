<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPayment extends Model
{
    protected $fillable = ['tenant_id', 'platform_invoice_id', 'provider', 'reference', 'amount_minor', 'currency', 'status', 'paid_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'paid_at' => 'immutable_datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'platform_invoice_id');
    }
}
