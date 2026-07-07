<?php

/**
 * Thin wrapper around cURL for talking to the backend REST API.
 * Every call returns ['status' => int, 'data' => array|null].
 */

function api_request(string $method, string $path, ?array $body = null, ?string $token = null): array
{
    $url = rtrim(API_BASE_URL, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($response === false) {
        return ['status' => 0, 'data' => ['success' => false, 'message' => "Could not reach the API: $error"]];
    }

    $decoded = json_decode($response, true);
    return ['status' => $status, 'data' => is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Invalid response from API.']];
}

function api_get(string $path, ?string $token = null): array
{
    return api_request('GET', $path, null, $token);
}

function api_post(string $path, array $body = [], ?string $token = null): array
{
    return api_request('POST', $path, $body, $token);
}

function api_put(string $path, array $body = [], ?string $token = null): array
{
    return api_request('PUT', $path, $body, $token);
}

function api_patch(string $path, array $body = [], ?string $token = null): array
{
    return api_request('PATCH', $path, $body, $token);
}

function api_delete(string $path, ?string $token = null): array
{
    return api_request('DELETE', $path, null, $token);
}
