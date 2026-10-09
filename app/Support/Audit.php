<?php

namespace App\Support;

use App\Models\ActivityLog;

class Audit
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        array $metadata = []
    ): void {
        if (! config('security.audit_enabled', true)) {
            return;
        }

        $user = auth()->user();

        ActivityLog::create([
            'user_id' => $user?->id,
            'role' => $user?->role,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => request()->ip() ?? '0.0.0.0',
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
