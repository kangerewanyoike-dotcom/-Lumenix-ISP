<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            $forbidden = array_diff(array_keys($invoice->getDirty()), ['status', 'balance_minor']);
            if ($forbidden !== []) {
                throw new \LogicException('Issued invoice totals and identity are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Issued invoices cannot be deleted.'));
    }

    protected $fillable = [
        'customer_id', 'number', 'status', 'period_start', 'period_end', 'issued_at', 'due_at', 'currency',
        'subtotal_minor', 'tax_minor', 'total_minor', 'balance_minor', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date', 'period_end' => 'immutable_date',
            'issued_at' => 'immutable_date', 'due_at' => 'immutable_date',
            'subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'balance_minor' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
