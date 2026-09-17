<?php
require __DIR__ . '/_bootstrap.php';
ensure_method('POST');
require_login();

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tidak ada file yang diupload']);
    exit;
}

$file = $_FILES['image'];
$allowedMimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$maxSizeBytes = 8 * 1024 * 1024; // 8MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload gagal (kode error: ' . $file['error'] . ')']);
    exit;
}

if ($file['size'] > $maxSizeBytes) {
    http_response_code(400);
    echo json_encode(['error' => 'File terlalu besar, maksimal 8MB']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedMimeToExt[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipe file tidak didukung. Gunakan JPG, PNG, atau WEBP']);
    exit;
}

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$ext = $allowedMimeToExt[$mime];
$filename = bin2hex(random_bytes(8)) . '.' . $ext;
$destination = UPLOAD_DIR . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan file di server']);
    exit;
}

$caption = isset($_POST['caption']) ? trim((string)$_POST['caption']) : '';

echo json_encode([
    'ok' => true,
    'url' => UPLOAD_URL_PREFIX . $filename,
    'caption' => $caption !== '' ? $caption : '(tanpa caption)'
]);
