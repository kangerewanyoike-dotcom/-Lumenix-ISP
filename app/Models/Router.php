<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'connection_mode', 'endpoint', 'agent_id', 'api_username', 'api_password', 'radius_secret', 'api_port', 'use_tls', 'status', 'last_seen_at', 'cpu_percent', 'memory_percent', 'uptime_seconds', 'download_bps', 'upload_bps', 'telemetry_updated_at',
    ];

    protected $hidden = ['api_username', 'api_password', 'radius_secret'];

    protected function casts(): array
    {
        return [
            'api_username' => 'encrypted',
            'api_password' => 'encrypted',
            'radius_secret' => 'encrypted',
            'api_port' => 'integer',
            'use_tls' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
            'cpu_percent' => 'float',
            'memory_percent' => 'float',
            'uptime_seconds' => 'integer',
            'download_bps' => 'integer',
            'upload_bps' => 'integer',
            'telemetry_updated_at' => 'immutable_datetime',
        ];
    }
}
