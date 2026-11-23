<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Issued invoice lines are immutable.'));
        static::deleting(fn () => throw new \LogicException('Issued invoice lines are immutable.'));
    }

    protected $fillable = [
        'invoice_id', 'description', 'quantity', 'unit_amount_minor', 'subtotal_minor',
        'tax_rate_basis_points', 'tax_minor', 'total_minor', 'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'unit_amount_minor' => 'integer', 'subtotal_minor' => 'integer',
            'tax_rate_basis_points' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
