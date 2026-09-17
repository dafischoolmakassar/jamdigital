<?php
require __DIR__ . '/_bootstrap.php';
ensure_method('POST');

$body = json_body();
$password = (string)($body['password'] ?? '');

$admin = read_json_file(ADMIN_FILE, null);
if ($admin === null || empty($admin['passwordHash'])) {
    // First-run: seed default admin password. WAJIB diganti lewat menu "Ganti Password".
    $admin = ['passwordHash' => password_hash('admin123', PASSWORD_DEFAULT)];
    write_json_file(ADMIN_FILE, $admin);
}

if ($password !== '' && password_verify($password, $admin['passwordHash'])) {
    session_regenerate_id(true);
    $_SESSION['dafi_admin'] = true;
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Password salah']);
}
