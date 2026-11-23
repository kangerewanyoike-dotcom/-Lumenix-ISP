<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DarajaAccount extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'environment', 'short_code', 'account_type', 'consumer_key', 'consumer_secret', 'passkey', 'callback_token', 'callback_token_hash', 'active'];

    protected function casts(): array
    {
        return ['consumer_key' => 'encrypted', 'consumer_secret' => 'encrypted', 'passkey' => 'encrypted', 'callback_token' => 'encrypted', 'active' => 'boolean'];
    }

    protected $hidden = ['consumer_key', 'consumer_secret', 'passkey', 'callback_token'];
}
