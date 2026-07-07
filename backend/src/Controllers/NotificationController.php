<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

class NotificationController
{
    /** GET /api/notifications?unread=1 */
    public static function index(Request $request): void
    {
        $unreadOnly = $request->input('unread') == '1';
        Response::success(Notification::forUser((int) $request->user['sub'], $unreadOnly));
    }

    /** PATCH /api/notifications/{id}/read */
    public static function markRead(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $notifications = Notification::forUser((int) $request->user['sub']);
        $owned = array_filter($notifications, fn($n) => (int) $n['id'] === $id);
        if (empty($owned)) {
            Response::error('Notification not found.', 404);
            return;
        }
        Notification::markRead($id);
        Response::success(null, 'Notification marked as read.');
    }
}
