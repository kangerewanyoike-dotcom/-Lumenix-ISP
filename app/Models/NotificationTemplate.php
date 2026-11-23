<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['event_key', 'channel', 'body', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
