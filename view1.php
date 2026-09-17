<?php
/**
 * Entry point tunggal untuk kiosk (View 1). Ini URL yang dipakai di
 * `chrome --kiosk https://domain/view1.php`, BUKAN membuka file .html
 * langsung -- supaya tema (Tipe 1/Tipe 2) & data dari Admin Panel terbaca.
 *
 * File .html (docs/view1_normal.html / docs/view1_normal_tipe2.html) tetap
 * dipakai apa adanya sebagai "skin" -- dibuka langsung pun masih jalan dengan
 * data demo hardcoded (untuk keperluan poles desain), lihat komentar
 * "window.__DAFI_CONFIG__" di masing-masing file.
 */
require __DIR__ . '/api/_config_store.php';

$config = get_config();
$theme = ($config['activeTheme'] ?? 'tipe1') === 'tipe2' ? 'tipe2' : 'tipe1';
$file = $theme === 'tipe2' ? 'view1_normal_tipe2.html' : 'view1_normal.html';

$html = file_get_contents(__DIR__ . '/docs/' . $file);
if ($html === false) {
    http_response_code(500);
    echo 'View file tidak ditemukan: ' . htmlspecialchars($file);
    exit;
}

$configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$headInject = "<script>window.__DAFI_CONFIG__ = {$configJson};</script>\n</head>";
$html = str_replace('</head>', $headInject, $html);

// Overlay + state machine untuk otomatis pindah ke View 2-5 (Menjelang Adzan,
// Adzan, Menjelang Iqomah, Sholat Berlangsung) berdasarkan jadwal sholat asli.
// Lihat docs/state-machine.js untuk logikanya.
$bodyInject = <<<HTML
    <div id="state-overlay" class="fixed inset-0 z-40 opacity-0 pointer-events-none transition-opacity duration-1000">
        <iframe id="state-iframe" class="w-full h-full border-0" title="Status Sholat"></iframe>
    </div>
    <script src="docs/state-machine.js"></script>
</body>
HTML;
$html = str_replace('</body>', $bodyInject, $html);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, must-revalidate');
echo $html;
