<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLedgerEntry extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Credit ledger entries are immutable.'));
        static::deleting(fn () => throw new \LogicException('Credit ledger entries are immutable.'));
    }

    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'invoice_id', 'amount_minor', 'currency', 'kind', 'description',
        'idempotency_key', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
