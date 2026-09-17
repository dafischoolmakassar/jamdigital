<?php
/**
 * Lapisan penyimpanan config berbasis file JSON.
 * Dipakai bersama oleh endpoint API (lewat _bootstrap.php) dan view1.php
 * (view1.php require file ini langsung, TANPA session/header, karena dia
 * melayani halaman publik kiosk, bukan endpoint API).
 */

define('DATA_DIR', __DIR__ . '/../data');
define('CONFIG_FILE', DATA_DIR . '/config.json');
define('ADMIN_FILE', DATA_DIR . '/admin.json');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL_PREFIX', 'uploads/');

function read_json_file(string $path, $default = []) {
    if (!file_exists($path)) return $default;
    $fp = fopen($path, 'r');
    if (!$fp) return $default;
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($content, true);
    return $data === null ? $default : $data;
}

function write_json_file(string $path, $data): void {
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    $fp = fopen($path, 'c');
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function default_config(): array {
    return [
        'activeTheme' => 'tipe1', // 'tipe1' | 'tipe2' -- dipilih dari Admin Panel
        'mosque' => [
            'name' => 'Masjid Jasma Digital',
            'address' => 'Cebongan Tlogoadi Mlati, DI Yogyakarta',
            'runningText' => "Sebaik-baik shalat seseorang adalah di rumahnya kecuali shalat wajib • Luruskan dan rapatkan shaf saat shalat berjamaah • Harap mematikan atau mengheningkan suara HP Anda selama berada di area masjid."
        ],
        // Jadwal hari ini -- diisi MANUAL oleh admin untuk sekarang.
        // Fase berikutnya (lihat PLANNING_MD Fase 3): field ini akan diisi otomatis dari Aladhan API + koreksi di bawah.
        'prayerSchedule' => [
            'imsak' => '04:18',
            'shubuh' => '04:28',
            'terbit' => '05:39',
            'dzuhur' => '11:49',
            'ashar' => '15:02',
            'maghrib' => '17:52',
            'isya' => '19:01'
        ],
        'prayerApi' => [
            'lat' => -7.7326,
            'lng' => 110.3591,
            'method' => 20,
            'correctionMinutes' => ['shubuh' => 2, 'dzuhur' => 1, 'ashar' => 1, 'maghrib' => 0, 'isya' => 1]
        ],
        'normalCarousel' => [
            'dashboardSeconds' => 30,
            'photoSeconds' => 30,
            'analogClockSeconds' => 30
        ],
        'timing' => [
            'preAdzanMinutes' => 10,
            'azanDurationMinutes' => ['shubuh' => 4, 'dzuhur' => 3, 'ashar' => 3, 'maghrib' => 3, 'isya' => 3],
            'iqomahOffsetMinutes' => ['shubuh' => 15, 'dzuhur' => 10, 'ashar' => 10, 'maghrib' => 5, 'isya' => 10],
            'sholatDurationMinutes' => ['shubuh' => 12, 'dzuhur' => 10, 'ashar' => 10, 'maghrib' => 8, 'isya' => 12],
            'jumat' => ['khutbahDurationMinutes' => 35, 'sholatDurationMinutes' => 10]
        ],
        'slideImages' => [
            ['url' => 'https://images.unsplash.com/photo-1542810634-71277d95dcbb?q=80&w=1200&auto=format&fit=crop', 'caption' => 'Masjid Nabawi, Madinah'],
            ['url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?q=80&w=1200&auto=format&fit=crop', 'caption' => 'Keindahan Arsitektur Islam']
        ]
    ];
}

function get_config(): array {
    if (!file_exists(CONFIG_FILE)) {
        write_json_file(CONFIG_FILE, default_config());
    }
    return read_json_file(CONFIG_FILE, default_config());
}
