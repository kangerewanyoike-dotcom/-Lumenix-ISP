<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformInvoice extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            $forbidden = array_diff(array_keys($invoice->getDirty()), ['status', 'balance_minor']);
            if ($forbidden !== []) {
                throw new \LogicException('Platform invoice identity and amount are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Platform invoices cannot be deleted.'));
    }

    protected $fillable = ['tenant_id', 'platform_subscription_id', 'usage_snapshot_id', 'number', 'status', 'period_start', 'period_end', 'issued_at', 'due_at', 'currency', 'total_minor', 'balance_minor', 'idempotency_key'];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'issued_at' => 'immutable_date', 'due_at' => 'immutable_date', 'total_minor' => 'integer', 'balance_minor' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscription::class, 'platform_subscription_id');
    }
}
