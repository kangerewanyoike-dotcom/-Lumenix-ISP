<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MessagingAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = ['environment', 'username', 'api_key', 'sender_id', 'active'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'active' => 'boolean'];
    }
}
