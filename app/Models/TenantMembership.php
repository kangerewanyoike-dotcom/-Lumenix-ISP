<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    public const ROLES = ['owner', 'admin', 'support', 'agent'];

    protected $fillable = ['tenant_id', 'user_id', 'role', 'status'];

    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            if (! in_array($membership->role, self::ROLES, true)) {
                throw new \InvalidArgumentException('Invalid tenant role.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
