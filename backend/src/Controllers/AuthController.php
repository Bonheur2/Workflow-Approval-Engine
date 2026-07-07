<?php

namespace App\Controllers;

use App\Core\Env;
use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;

class AuthController
{
    /**
     * POST /api/auth/register
     * Open registration always creates a "requester" - the lowest
     * privilege role. Admins are provisioned via the seed script or by
     * an existing admin through PATCH /api/users/{id}/role, so nobody
     * can self-elevate through this endpoint.
     */
    public static function register(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }

        if (User::findByEmail($data['email'])) {
            Response::error('An account with that email already exists.', 409);
            return;
        }

        $id = User::create($data['name'], $data['email'], $data['password'], 'requester');
        $user = User::sanitize(User::find($id));
        Response::success($user, 'Account created.', 201);
    }

    /** POST /api/auth/login */
    public static function login(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }

        $user = User::findByEmail($data['email']);
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            Response::error('Invalid credentials.', 401);
            return;
        }
        if (!$user['is_active']) {
            Response::error('This account has been deactivated.', 403);
            return;
        }

        $ttl = (int) Env::get('JWT_TTL_SECONDS', 3600);
        $token = JWT::encode([
            'sub' => (int) $user['id'],
            'role' => $user['role'],
            'name' => $user['name'],
            'email' => $user['email'],
        ], $ttl);

        Response::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => User::sanitize($user),
        ], 'Login successful.');
    }

    /** GET /api/auth/me */
    public static function me(Request $request): void
    {
        $user = User::find((int) $request->user['sub']);
        if (!$user) {
            Response::error('User not found.', 404);
            return;
        }
        Response::success(User::sanitize($user));
    }
}
