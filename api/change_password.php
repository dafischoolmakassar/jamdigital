<?php
require __DIR__ . '/_bootstrap.php';
ensure_method('POST');
require_login();

$body = json_body();
$currentPassword = (string)($body['currentPassword'] ?? '');
$newPassword = (string)($body['newPassword'] ?? '');

$admin = read_json_file(ADMIN_FILE, null);
if ($admin === null || empty($admin['passwordHash']) || !password_verify($currentPassword, $admin['passwordHash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Password saat ini salah']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Password baru minimal 6 karakter']);
    exit;
}

write_json_file(ADMIN_FILE, ['passwordHash' => password_hash($newPassword, PASSWORD_DEFAULT)]);
echo json_encode(['ok' => true]);
