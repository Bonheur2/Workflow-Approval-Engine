<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public static function log(
        int $requestId,
        string $action,
        int $userId,
        ?string $previousStatus,
        ?string $newStatus,
        ?string $comments = null
    ): void {
        AuditLog::record($requestId, $action, $userId, $previousStatus, $newStatus, $comments);
    }
}
