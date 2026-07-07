<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Generates notifications for the events required by the spec:
 * submitted, approved, rejected, returned, awaiting-approval.
 *
 * The delivery mechanism is intentionally just a database-backed inbox
 * (exposed via GET /notifications) rather than email/SMS/push, per the
 * challenge's note that "the implementation method is left to your
 * discretion." This keeps the engine runnable with zero external
 * services while still making every event fully traceable and testable.
 */
class NotificationService
{
    public static function requestSubmitted(int $requesterId, int $requestId): void
    {
        Notification::create($requesterId, $requestId, 'request_submitted', "Your request #$requestId has been submitted.");
    }

    public static function awaitingApproval(int $approverId, int $requestId): void
    {
        Notification::create($approverId, $requestId, 'awaiting_approval', "Request #$requestId is awaiting your approval.");
    }

    public static function requestApproved(int $requesterId, int $requestId): void
    {
        Notification::create($requesterId, $requestId, 'request_approved', "Your request #$requestId has been approved.");
    }

    public static function requestRejected(int $requesterId, int $requestId, ?string $reason): void
    {
        $suffix = $reason ? " Reason: $reason" : '';
        Notification::create($requesterId, $requestId, 'request_rejected', "Your request #$requestId has been rejected.$suffix");
    }

    public static function requestReturned(int $requesterId, int $requestId, ?string $reason): void
    {
        $suffix = $reason ? " Reason: $reason" : '';
        Notification::create($requesterId, $requestId, 'request_returned', "Your request #$requestId was returned for modification.$suffix");
    }
}
