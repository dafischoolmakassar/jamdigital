<?php
/**
 * Dipakai oleh semua endpoint di /api/*.php.
 * Menyediakan session admin, helper request JSON, dan auth guard.
 */

require __DIR__ . '/_config_store.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_login(): void {
    if (empty($_SESSION['dafi_admin'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized -- silakan login dulu']);
        exit;
    }
}

function ensure_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
}
