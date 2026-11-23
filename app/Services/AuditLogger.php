<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

final class AuditLogger
{
    /** @var list<string> */
    private const SAFE_METADATA_KEYS = [
        'invoice_id', 'adjustment_minor', 'billing_day', 'amount_minor', 'environment', 'short_code',
        'customer_id', 'automation_enabled', 'grace_period_days', 'active', 'connection_mode',
        'tenant_id', 'event_key', 'channel', 'status', 'format', 'role',
    ];

    public function record(string $action, ?int $tenantId = null, ?string $subjectType = null, ?string $subjectId = null, array $metadata = []): void
    {
        $safeMetadata = array_intersect_key($metadata, array_fill_keys(self::SAFE_METADATA_KEYS, true));
        $safeMetadata = array_filter($safeMetadata, fn ($value): bool => is_int($value) || is_bool($value) || (is_string($value) && strlen($value) <= 120));

        AuditLog::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $safeMetadata,
            'created_at' => now(),
        ]);
    }
}
