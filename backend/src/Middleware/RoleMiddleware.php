<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class RoleMiddleware
{
    /**
     * Must run AFTER AuthMiddleware, since it relies on $request->user
     * having already been populated.
     */
    public static function handle(string ...$allowedRoles): callable
    {
        return function (Request $request) use ($allowedRoles) {
            $role = $request->user['role'] ?? null;
            if (!$role || !in_array($role, $allowedRoles, true)) {
                Response::error('You do not have permission to perform this action.', 403);
                return false;
            }
            return true;
        };
    }
}
