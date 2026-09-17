<?php
/**
 * Utilitas sekali-jalan untuk generate ikon PWA (siluet masjid sederhana,
 * navy + amber sesuai brand). Jalankan manual lewat CLI kalau perlu
 * regenerate/ganti desain: `php scripts/generate_icons.php`.
 * Output disimpan ke /icons/icon-192.png dan /icons/icon-512.png.
 */

function drawMosqueIcon(int $size): \GdImage {
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    $navy = imagecolorallocate($img, 10, 15, 28);      // #0a0f1c
    $amber = imagecolorallocate($img, 245, 158, 11);    // #f59e0b
    $amberDark = imagecolorallocate($img, 217, 119, 6); // #d97706

    // Background
    imagefilledrectangle($img, 0, 0, $size, $size, $navy);

    $cx = $size / 2;
    $baseY = $size * 0.68;
    $baseH = $size * 0.20;
    $baseW = $size * 0.46;

    // Base bangunan
    imagefilledrectangle($img, (int)($cx - $baseW / 2), (int)$baseY, (int)($cx + $baseW / 2), (int)($baseY + $baseH), $amber);

    // Pintu (lengkung sederhana pakai ellipse + rectangle)
    $doorW = $size * 0.1;
    $doorH = $baseH * 0.75;
    imagefilledellipse($img, (int)$cx, (int)($baseY + $baseH - $doorH), (int)$doorW, (int)$doorW, $navy);
    imagefilledrectangle($img, (int)($cx - $doorW / 2), (int)($baseY + $baseH - $doorH / 2), (int)($cx + $doorW / 2), (int)($baseY + $baseH), $navy);

    // Kubah utama
    $domeR = $size * 0.19;
    $domeCy = $baseY - $domeR * 0.55;
    imagefilledellipse($img, (int)$cx, (int)$domeCy, (int)($domeR * 2), (int)($domeR * 2), $amber);

    // Puncak kubah (garis kecil)
    imagefilledrectangle($img, (int)($cx - $size * 0.01), (int)($domeCy - $domeR - $size * 0.06), (int)($cx + $size * 0.01), (int)($domeCy - $domeR), $amberDark);
    imagefilledellipse($img, (int)$cx, (int)($domeCy - $domeR - $size * 0.07), (int)($size * 0.035), (int)($size * 0.035), $amberDark);

    // Menara kiri & kanan
    foreach ([-1, 1] as $side) {
        $towerX = $cx + $side * $size * 0.32;
        $towerW = $size * 0.07;
        $towerTopY = $baseY - $size * 0.18;

        imagefilledrectangle($img, (int)($towerX - $towerW / 2), (int)$towerTopY, (int)($towerX + $towerW / 2), (int)($baseY + $baseH), $amber);
        imagefilledellipse($img, (int)$towerX, (int)$towerTopY, (int)($towerW * 1.6), (int)($towerW * 1.6), $amberDark);
    }

    return $img;
}

if (!is_dir(__DIR__ . '/../icons')) {
    mkdir(__DIR__ . '/../icons', 0755, true);
}

foreach ([192, 512] as $size) {
    $img = drawMosqueIcon($size);
    $path = __DIR__ . "/../icons/icon-{$size}.png";
    imagepng($img, $path);
    imagedestroy($img);
    echo "Dibuat: {$path}\n";
}
