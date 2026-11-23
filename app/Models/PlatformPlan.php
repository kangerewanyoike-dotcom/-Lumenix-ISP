<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformPlan extends Model
{
    protected $fillable = ['name', 'monthly_price_minor', 'currency', 'included_subscribers', 'max_routers', 'features', 'active'];

    protected function casts(): array
    {
        return ['monthly_price_minor' => 'integer', 'included_subscribers' => 'integer', 'max_routers' => 'integer', 'features' => 'array', 'active' => 'boolean'];
    }
}
