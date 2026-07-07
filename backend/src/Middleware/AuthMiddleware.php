<?php

namespace App\Middleware;

use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;

class AuthMiddleware
{
    /**
     * Returns a callable middleware. On success it attaches the decoded
     * user payload to $request->user. On failure it writes a 401 response
     * and returns false so the router stops the pipeline.
     */
    public static function handle(): callable
    {
        return function (Request $request) {
            $token = $request->bearerToken();
            if (!$token) {
                Response::error('Authentication token missing.', 401);
                return false;
            }
            $payload = JWT::decode($token);
            if (!$payload) {
                Response::error('Invalid or expired token.', 401);
                return false;
            }
            $request->user = $payload;
            return true;
        };
    }
}
