<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'price_minor', 'currency', 'speed_down_kbps', 'speed_up_kbps', 'billing_period', 'data_cap_bytes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'speed_down_kbps' => 'integer',
            'speed_up_kbps' => 'integer',
            'data_cap_bytes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
