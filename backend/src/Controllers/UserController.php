<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;

class UserController
{
    /** GET /api/users - admin only */
    public static function index(Request $request): void
    {
        $users = array_map([User::class, 'sanitize'], User::all());
        Response::success($users);
    }

    /** GET /api/users/{id} */
    public static function show(Request $request, array $params): void
    {
        $user = User::find((int) $params['id']);
        if (!$user) {
            Response::error('User not found.', 404);
            return;
        }
        Response::success(User::sanitize($user));
    }

    /** POST /api/users - admin creates a user with any role directly */
    public static function store(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:' . implode(',', User::ROLES),
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }
        if (User::findByEmail($data['email'])) {
            Response::error('An account with that email already exists.', 409);
            return;
        }
        $id = User::create($data['name'], $data['email'], $data['password'], $data['role']);
        Response::success(User::sanitize(User::find($id)), 'User created.', 201);
    }

    /** PATCH /api/users/{id}/role - admin only */
    public static function updateRole(Request $request, array $params): void
    {
        $data = $request->all();
        $v = Validator::make($data, ['role' => 'required|in:' . implode(',', User::ROLES)]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }
        $id = (int) $params['id'];
        if (!User::find($id)) {
            Response::error('User not found.', 404);
            return;
        }
        User::updateRole($id, $data['role']);
        Response::success(User::sanitize(User::find($id)), 'Role updated.');
    }

    /** PATCH /api/users/{id}/status - admin only, activate/deactivate */
    public static function updateStatus(Request $request, array $params): void
    {
        $data = $request->all();
        $id = (int) $params['id'];
        if (!User::find($id)) {
            Response::error('User not found.', 404);
            return;
        }
        User::setActive($id, (bool) ($data['is_active'] ?? true));
        Response::success(User::sanitize(User::find($id)), 'Status updated.');
    }
}
