<?php
/**
 * Endpoint waktu server -- dipakai kiosk utk koreksi jam device yang salah
 * atau drift (umum terjadi di Android TV box murah tanpa RTC baterai,
 * reset ke waktu salah tiap kali listrik mati-nyala). Return epoch ms
 * (UTC, universal, tidak tergantung setting timezone device manapun).
 * Tidak perlu login -- ini data publik (jam saat ini), read-only.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['epochMs' => (int) round(microtime(true) * 1000)]);
