<?php

/**
 * Thin helpers over PHP's native session for holding the JWT + user
 * profile, plus one-shot "flash" messages for form feedback.
 */

function app_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_token(): ?string
{
    return $_SESSION['token'] ?? null;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_token() !== null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user && in_array($user['role'], $roles, true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        flash_error('You do not have permission to view that page.');
        redirect('index.php');
    }
}

function log_in(string $token, array $user): void
{
    $_SESSION['token'] = $token;
    $_SESSION['user'] = $user;
}

function log_out(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_error(string $message): void
{
    $_SESSION['flash_error'] = $message;
}

function flash_success(string $message): void
{
    $_SESSION['flash_success'] = $message;
}

function take_flash(): array
{
    $error = $_SESSION['flash_error'] ?? null;
    $success = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    return ['error' => $error, 'success' => $success];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
