<?php
require __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(get_config());
    exit;
}

if ($method === 'POST') {
    require_login();
    $incoming = json_body();

    if (empty($incoming) || !isset($incoming['mosque'], $incoming['timing'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payload config tidak valid']);
        exit;
    }

    // activeTheme hanya boleh salah satu dari 2 nilai yang valid
    if (!in_array($incoming['activeTheme'] ?? '', ['tipe1', 'tipe2'], true)) {
        $incoming['activeTheme'] = 'tipe1';
    }

    write_json_file(CONFIG_FILE, $incoming);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
