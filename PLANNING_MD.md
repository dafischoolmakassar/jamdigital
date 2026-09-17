# Rencana Pengembangan: Dafi Digital — Display Jam & Waktu Sholat Masjid

## 0. Keputusan yang Sudah Dikonfirmasi

| Topik | Keputusan |
|---|---|
| Target device | 1 layar per masjid (browser fullscreen di TV/Android box/mini PC). Tidak ada sinkronisasi multi-layar/backend realtime. |
| Sumber jadwal sholat | Fetch otomatis dari API publik (Aladhan API), dengan koreksi manual (ihtiyat) per waktu karena hasil hitung API bisa selisih 1–3 menit dari jadwal resmi Kemenag setempat. |
| Audio adzan | Beep/chime (Web Audio API) untuk MVP — sama seperti prototype sekarang. Audio adzan asli (mp3) jadi enhancement fase berikutnya. |
| Update gambar & jadwal | Ada halaman **Admin** terpisah (bukan edit file config manual) untuk upload gambar slideshow & atur koreksi jadwal — konsekuensinya tetap butuh backend ringan meskipun display-nya cuma 1 layar. |
| Hosting | Shared hosting (cPanel-style) — **tidak** tersedia Node.js/VPS. Backend wajib **PHP + SQLite/JSON file**, bukan Node/Express. |
| Fullscreen | **Kiosk mode Chrome** di device (`chrome --kiosk https://domain/view1.php`), bukan trik JS Fullscreen API — browser tidak izinkan fullscreen otomatis tanpa gesture user, kiosk mode di level OS/device yang menyelesaikan ini. |
| Mode Normal (View 1) | Bukan 1 tampilan statis — berputar otomatis 3 sub-tampilan: **Dashboard → Foto Acak fullscreen → Jam Analog fullscreen → ulang**, dengan durasi tiap sub-tampilan **bisa diatur dari Admin Panel** (default 30 detik masing-masing). |
| Auto-refresh kiosk | Karena device TV pakai browser biasa (bukan Fully Kiosk Browser yang punya *Scheduled Restart* sendiri), `docs/state-machine.js` fetch ulang config tiap **2 menit** (`AUTO_RELOAD_MINUTES`) dan update tampilan langsung lewat `window.__DAFI_APPLY_CONFIG__` — **bukan `location.reload()`**, supaya fullscreen (klik manual atau PWA) tidak pernah ke-reset. Hanya jalan saat state NORMAL. Pengecualian: kalau **Tema** (Tipe 1/Tipe 2) yang berubah, itu tetap butuh reload penuh karena beda file HTML. |
| Sinkronisasi jam | Kiosk **tidak 100% percaya jam sistem device TV** (Android TV box murah sering tidak punya RTC baterai, jam bisa salah/reset tiap listrik mati-nyala). `api/time.php` + `docs/state-machine.js` sinkron ke jam server tiap 15 menit, hitung selisih (`clockOffsetMs`), dan seluruh perhitungan state + jam yang ditampilkan (View 1, dan View 2-5 lewat parameter `correctedNowMs`) pakai jam terkoreksi ini, bukan `new Date()` polos. |

---

## 1. Hasil Review Plan/Prototype Sebelumnya (Gap yang Ditemukan)

File yang sudah ada di folder ini:
- `dafi_digital_display_masjid.html` (+ varian `(1)`, beda ukuran font jam saja) → ini view **NORMAL**
- `dafi_digital_countdown_azan.html` → ini sebenarnya view **MENJELANG ADZAN** (ring countdown), BUKAN view "adzan" itu sendiri
- `dafi_digital_mode_azan.html` → ini view **ADZAN BERKUMANDANG** (kaligrafi + progress bar), audio masih beep placeholder

> **Update:** kelima view di atas sudah difiksasi & diberi label pada 2026-09-17 — lihat Bagian 1.1 di bawah untuk nama file final.

Masalah pada draft plan sebelumnya:
1. **Penamaan state tidak match dengan file yang ada** — plan lama menyebut 3–4 state generik tanpa memetakan ke 3 file di atas, padahal masing-masing file punya tujuan yang berbeda.
2. **2 state belum punya mockup sama sekali**: *Menjelang Iqomah* (jeda antara selesai adzan → iqomah) dan *Sholat Berlangsung* (view kaligrafi murni, tanpa timer/countdown, karena saat ini jamaah sedang sholat dan layar tidak boleh mengganggu).
3. **Tidak ada logika "kembali ke Normal"** — plan lama cuma bilang "kembali ke NORMAL" tanpa mendefinisikan berapa lama sholat berlangsung per waktu (Subuh, Dzuhur, Ashar, Maghrib, Isya beda durasi; Jumat beda total alur karena ada khutbah).
4. **3 file terpisah, masing-masing punya jam/tanggal/marquee sendiri** — kalau dipakai apa adanya di production, pindah antar "view" berarti pindah halaman HTML (reload), yang akan mereset timer dan bikin layar berkedip. Untuk display yang menyala 24 jam, ini harus jadi **1 aplikasi (SPA)** dengan komponen yang saling switch, bukan 3 halaman terpisah.
5. **Jadwal sholat & gambar slideshow hardcoded di JS** — tidak ada mekanisme update tanpa edit kode.
6. **Tidak ada sumber jadwal sholat yang scalable** — perlu API + koreksi manual.
7. **Panel simulasi/demo** (`Panel Simulasi Demo`, `Panel Kontrol Demo`) ada di 2 file prototype — perlu disembunyikan di production (mode `?dev=1` saja), jangan tampil ke jamaah.
8. **Gambar slideshow masih hotlink ke Unsplash** — berisiko blank kalau internet masjid putus atau URL berubah; untuk production sebaiknya gambar di-hosting sendiri (via admin upload).
9. **Tidak ada watchdog** untuk kiosk yang menyala terus-menerus (memory leak dari `setInterval` yang berjalan berhari-hari, jam sistem browser drift, dsb).

---

## 1.1 Fiksasi Nama File per View (2026-09-17)

Kelima view sekarang masing-masing punya 1 file HTML sendiri, sudah diberi label on-screen (badge kecil pojok kiri-atas, `pointer-events-none`, aman dihapus saat build production) supaya gampang dibedakan saat dipoles satu-satu. File lama yang terduplikasi (`dafi_digital_display_masjid.html` versi jam berbingkai) dipindah ke `archive/` — bukan dihapus, jaga-jaga masih perlu direferensikan.

> **Update 2026-09-17 (lanjutan):** semua file HTML di tabel bawah (termasuk `archive/`) sudah dipindah ke dalam folder **`docs/`** — jadi path lengkapnya `docs/view1_normal.html`, `docs/admin_settings.html`, dst. Nama di kolom "File" di bawah ini tanpa prefix `docs/` supaya ringkas, tapi lokasi fisiknya semua di dalam `docs/`.

| View | File (di dalam `docs/`) | Status polish |
|---|---|---|
| View 1 — Normal | `view1_normal.html` | Sudah ada, siap dipoles |
| View 2 — Menjelang Adzan | `view2_menjelang_adzan.html` | Sudah ada, siap dipoles |
| View 3 — Adzan Berkumandang | `view3_adzan.html` | Sudah ada, siap dipoles |
| View 4 — Menjelang Iqomah | `view4_menjelang_iqomah.html` | **Baru dibuat** (adaptasi dari View 2, aksen warna emerald supaya beda dari View 2) — perlu direview |
| View 4 — Menjelang Iqomah (Tipe 2) | `view4_menjelang_iqomah_tipe2.html` | **Varian desain alternatif** (2026-09-17) — timer MM:SS besar (gaya kotak seperti View 3) + progress bar linear, bukan angka kecil di dalam spinner/ring seperti Tipe 1. Belum disambungkan ke state machine (`state-machine.js` masih pakai Tipe 1 secara default) — perlu keputusan mana yang dipakai final atau dijadikan opsi tema seperti View 1. |
| View 5 — Sholat Berlangsung | `view5_sholat_berlangsung.html` | **Baru dibuat** (kaligrafi/ayat bergantian, tanpa countdown & tanpa jam besar sesuai catatan risiko #kekhusyukan) — perlu direview |
| *(arsip)* | `archive/dafi_digital_display_masjid_boxed-clock.html` | Versi lama View 1, disimpan sebagai referensi |
| Admin Panel | `admin_settings.html` | **Sudah tersambung ke backend asli** (2026-09-17) — form Profil Masjid (+ pemilih Tema View 1 + ganti password), Jadwal Sholat (manual harian + pengaturan API untuk fase depan), Durasi & Transisi View, Slideshow Gambar (upload asli ke server), Preview Config JSON. Login & data tidak lagi pakai `localStorage`/password hardcoded — lihat Bagian 4.1 untuk detail backend. |
| Backend | `api/*.php`, `data/`, `uploads/`, `view1.php` | **Dibangun** (2026-09-17) — lihat Bagian 4.1. |
| View 1 — Normal (Tipe 2) | `view1_normal_tipe2.html` | **Varian desain alternatif** (2026-09-17), dibuat dari referensi foto display masjid nyata milik user: bar jadwal sholat warna solid penuh (bukan tinted card + ikon), tambahan baris **Imsak** (informasi saja, tidak ikut hitung mundur). Palet direvisi 2x (2026-09-17): (1) hindari pink/ungu → Imsak (stone), Shubuh (biru), Syuruq (slate), Dzuhur (hitam+emas), Ashar (oranye), Maghrib (maroon, sengaja dipertahankan sebagai aksen senja), Isya (emerald), aksen countdown/dot/badge pakai amber, jam pakai `font-semibold` (tidak terlalu bold); (2) tema dasar header/date-bar/panel jam/footer diganti dari coklat tua ke **navy gelap**. Fungsi (slideshow, countdown, sub-rotasi Dashboard/Foto/Jam Analog) identik dengan `view1_normal.html` — hanya beda skin. User memilih salah satu Tipe untuk dipakai final, atau dijadikan opsi tema yang bisa dipilih dari Admin Panel. |

## 2. Peta Lengkap View / State

| # | State | Trigger | File | Status | Perilaku |
|---|-------|---------|-------------------|--------|----------|
| 1 | **NORMAL** | Default, di luar jendela waktu sholat | `view1_normal.html` | Sudah ada | Jam realtime, slideshow gambar fullscreen, jadwal 6 waktu sholat, countdown ke sholat berikutnya, marquee info |
| 2 | **MENJELANG_ADZAN** | X menit sebelum adzan (misal 5–10 menit, configurable) | `view2_menjelang_adzan.html` | Sudah ada | Ring countdown besar, nama sholat target, ajakan wudhu/shaf |
| 3 | **ADZAN** | Waktu adzan tiba s/d durasi adzan selesai (±3–5 menit, configurable per waktu) | `view3_adzan.html` | Sudah ada (audio masih beep) | Kaligrafi "Allahu Akbar", progress bar durasi adzan, beep saat mulai/selesai |
| 4 | **MENJELANG_IQOMAH** | Setelah adzan selesai s/d iqomah | `view4_menjelang_iqomah.html` | Draf baru, perlu dipoles | Reuse gaya ring countdown (mirip state 2) tapi label "Menjelang Iqomah", target waktu = adzan selesai + `iqomahOffset[waktu]` |
| 5 | **SHOLAT_BERLANGSUNG** | Saat iqomah dikumandangkan s/d estimasi sholat selesai | `view5_sholat_berlangsung.html` | Draf baru, perlu dipoles | Kaligrafi/ayat bergantian (cross-fade), tanpa angka countdown, tanpa jam besar (supaya tidak mengganggu kekhusyukan) — layar setenang mungkin |
| 6 | *(kembali ke)* **NORMAL** | Setelah `sholatDuration[waktu]` terlewati sejak iqomah | `view1_normal.html` | Logika baru | Balik ke state 1, slideshow lanjut dari slide terakhir |

Catatan khusus:
- **Terbit (syuruq)** tidak punya adzan/iqomah — cukup tampil di kartu jadwal NORMAL, tidak memicu state 2–6.
- **Jumat**: Dzuhur diganti alur Jumatan (adzan → khutbah ~30–40 menit → iqomah → sholat). `SHOLAT_BERLANGSUNG` untuk Jumat butuh durasi konfigurasi terpisah yang jauh lebih panjang, dan idealnya ada state tambahan "Khutbah" (opsional, bisa pakai view kaligrafi yang sama dulu untuk MVP).

### 2.1 Sub-Rotasi di Dalam State NORMAL (View 1)

Selama state **NORMAL** aktif (di luar jendela menjelang/selama sholat), View 1 **tidak diam di satu tampilan** — ia berputar otomatis di antara 3 sub-tampilan, sudah diimplementasikan di `view1_normal.html`:

```
Dashboard (header + slideshow + jadwal 6 waktu + marquee)
    │  (setelah dashboardSeconds, default 30s)
    ▼
Foto Acak Fullscreen  (1 gambar dari galeri admin, dipilih random tiap putaran)
    │  (setelah photoSeconds, default 30s)
    ▼
Jam Analog Fullscreen (jam analog SVG + tanggal, tanpa dashboard chrome)
    │  (setelah analogClockSeconds, default 30s)
    ▼
   kembali ke Dashboard (ulangi, dengan foto acak baru tiap putaran)
```

- Implementasi: 3 `<div>` sub-screen bertumpuk (`#screen-dashboard`, `#screen-photo`, `#screen-clock`) di-cross-fade via `opacity`/`pointer-events`, dikendalikan `runNormalCarousel()` (chained `setTimeout`, bukan `setInterval` tunggal, supaya durasi tiap sub-tampilan bisa berbeda).
- Ketiga durasi (`dashboardSeconds`, `photoSeconds`, `analogClockSeconds`) **dapat diatur dari Admin Panel** (lihat Bagian 5 & `admin_settings.html`), default 30 detik masing-masing.
- Sub-rotasi ini **berhenti otomatis** ketika state machine utama pindah ke MENJELANG_ADZAN/ADZAN/dst — perlu dipastikan `clearTimeout(carouselTimer)` dipanggil saat transisi keluar dari NORMAL (dicatat sebagai task di Fase 2, lihat Bagian 7).

---

## 3. Alur Transisi

```
NORMAL
  │  (now >= waktu_adzan - preAdzanMinutes)
  ▼
MENJELANG_ADZAN  ──(now >= waktu_adzan)──▶ ADZAN
                                             │ (now >= waktu_adzan + azanDuration)
                                             ▼
                                     MENJELANG_IQOMAH
                                             │ (now >= waktu_adzan + iqomahOffset[waktu])
                                             ▼
                                     SHOLAT_BERLANGSUNG
                                             │ (now >= iqomah_time + sholatDuration[waktu])
                                             ▼
                                          NORMAL  (loop harian, lanjut ke waktu sholat berikutnya)
```

State machine dievaluasi setiap detik dari satu sumber waktu (`Date.now()`), bukan dari page reload — supaya transisi presisi dan tidak ada state yang "kelewat" kalau tab sempat freeze sebentar.

---

## 4. Arsitektur & Teknologi

### Kiosk App (layar utama di masjid)
- **Bukan SPA penuh seperti rencana awal** — realisasinya `view1.php` (View 1, Normal) + **overlay `<iframe>`** yang dimuat dari `state-machine.js` untuk View 2-5 saat dibutuhkan. Ini dipilih ketimbang menggabung semua script ke 1 halaman karena tiap view awalnya dibangun sebagai file berdiri sendiri dengan nama variabel/fungsi yang sama (`updateRealtimeClock`, `daysIndo`, dst) — iframe memberi tiap view scope JS terpisah tanpa perlu namespace ulang semuanya, dengan trade-off overhead render iframe yang minor untuk skala 1 kiosk.
- **HTML5 + Tailwind CSS (CDN)** — konsisten dengan prototype yang sudah ada, tidak perlu build step/bundler untuk kiosk device yang sederhana.
- **Vanilla JavaScript**, dipecah per tanggung jawab:
  - `docs/state-machine.js` — hitung state (`NORMAL`/`MENJELANG_ADZAN`/`ADZAN`/`MENJELANG_IQOMAH`/`SHOLAT`) dari config + jam sekarang, kendalikan iframe overlay & sub-rotasi View 1 (lihat Fase 2 di Bagian 7 untuk detail)
  - Tiap `docs/view*.html` tetap punya script sendiri (jam, tanggal, countdown, dst) — dibaca lewat query string kalau dimuat sebagai iframe oleh state machine, atau data demo hardcoded kalau dibuka standalone
  - `audio.js` — **belum dibuat sebagai file terpisah**; beep/chime saat ini inline di tiap view (`playBeepSound()`), titik ekstensi untuk audio adzan asli nanti tetap sama
- **Prayer Schedule**: saat ini **manual** lewat Admin Panel (`config.prayerSchedule`), belum fetch API — integrasi Aladhan API (`method=20` untuk pendekatan Kemenag RI, refresh 1x/hari + fallback cache) masih rencana Fase 3, belum dikerjakan.
- **FontAwesome 6 + Google Fonts** (Inter, Plus Jakarta Sans, Orbitron, Amiri) — dipertahankan dari prototype.
- Kiosk dijalankan **fullscreen via Chrome kiosk mode** (`chrome --kiosk https://domain/view1.php`) di Android TV box/mini PC, resolusi target 1920×1080 landscape — bukan trik Fullscreen API dari JS, karena browser modern menolak fullscreen otomatis tanpa gesture user. Kiosk mode menyelesaikan ini di level device/OS.
- **Watchdog**: auto-reload halaman 1x/hari di jam sepi (misal jam 02:00) untuk mencegah memory leak dari proses yang berjalan 24 jam.

### Admin Panel (terpisah, diakses dari HP/PC pengurus masjid)
Karena keputusannya "ada halaman admin", ini butuh backend ringan meskipun kiosk-nya cuma 1 layar — supaya config yang diedit dari HP admin bisa dibaca oleh device kiosk di masjid (tidak bisa hanya `localStorage`, karena itu device-specific).

- **Backend minimal**: REST API sederhana dengan **PHP + SQLite/JSON file** — dikonfirmasi karena hosting yang tersedia saat ini shared hosting (cPanel-style, tanpa Node.js/VPS). Tidak perlu database besar untuk skala 1 masjid.
- **Fitur admin**:
  - Upload/hapus/reorder gambar slideshow (disimpan di server, bukan hotlink eksternal)
  - Edit profil masjid (nama, alamat, teks berjalan/marquee)
  - Koreksi manual jadwal sholat (menit ihtiyat per waktu) di atas hasil API
  - Atur `preAdzanMinutes`, `azanDuration`, `iqomahOffset[waktu]`, `sholatDuration[waktu]`
  - Login sederhana (1 password admin, disimpan hashed) — tidak perlu multi-user untuk MVP
- Kiosk app polling config dari endpoint admin ini setiap beberapa menit (atau saat reload harian), dengan fallback ke config terakhir yang berhasil diambil jika server/API tidak bisa diakses.

### 4.1 Backend yang Sudah Dibangun (2026-09-17)

```
jamdigital/
├── view1.php                     ← URL KIOSK (bukan docs/view1_normal.html langsung!)
├── docs/                         ← SEMUA file HTML frontend dikumpulkan di sini
│   ├── view1_normal.html         ← skin Tipe 1, tetap bisa dibuka langsung utk poles desain
│   ├── view1_normal_tipe2.html   ← skin Tipe 2, sama
│   ├── view2_menjelang_adzan.html
│   ├── view3_adzan.html
│   ├── view4_menjelang_iqomah.html
│   ├── view5_sholat_berlangsung.html
│   ├── admin_settings.html       ← Admin Panel, konsumsi ../api/*.php
│   └── archive/                  ← prototype lama, disimpan sbg referensi
├── api/
│   ├── _config_store.php         ← baca/tulis data/config.json, default_config()
│   ├── _bootstrap.php            ← session PHP + auth guard, dipakai endpoint lain
│   ├── config.php                ← GET (publik) / POST (butuh login) config
│   ├── login.php / logout.php / session.php
│   ├── change_password.php
│   └── upload.php                ← terima file gambar, simpan ke uploads/
├── data/
│   ├── config.json                ← 1 file JSON = seluruh config (mosque, jadwal, timing, tema, dst)
│   ├── admin.json                 ← password admin (hashed, `password_hash()`)
│   └── .htaccess                  ← blokir akses langsung dari browser ke folder ini
└── uploads/                       ← gambar slideshow hasil upload admin (publik, dibaca kiosk)
```

> **Catatan path**: `view1.php`, `api/`, `data/`, dan `uploads/` tetap di root (bukan ikut pindah ke `docs/`) supaya URL publik (`/view1.php`, `/api/...`, `/uploads/...`) tidak berubah. Hanya file HTML murni yang dipindah ke `docs/` — `docs/admin_settings.html` sudah disesuaikan supaya panggilan ke API pakai `../api/...` dan preview gambar upload pakai `../uploads/...`.

**Bagaimana `view1.php` memilih Tipe 1/Tipe 2:** baca `data/config.json` → field `activeTheme` (`'tipe1'` atau `'tipe2'`) → ambil isi file `view1_normal.html` atau `view1_normal_tipe2.html` apa adanya → sisipkan `<script>window.__DAFI_CONFIG__ = {...}</script>` sebelum `</head>` → kirim ke browser. Kedua file skin sudah dimodifikasi supaya baca `window.__DAFI_CONFIG__` dulu (mosque info, jadwal, gambar slideshow, durasi carousel) dan baru jatuh ke data demo hardcoded kalau variabel itu tidak ada (yaitu saat file dibuka langsung, bukan lewat `view1.php` — jadi alur "buka 1 file HTML, poles desain langsung" yang sudah berjalan sebelumnya tetap jalan).

**Alur upload gambar dari Admin Panel:**
1. Admin pilih tab "Slideshow Gambar", pilih file (JPG/PNG/WEBP, maks 8MB) + isi caption, klik "Tambah ke Slideshow".
2. Browser kirim file itu (`multipart/form-data`) ke `api/upload.php`.
3. `upload.php` cek: sudah login? tipe file valid (dicek dari isi file pakai `finfo`, bukan cuma ekstensi)? ukuran ≤ 8MB? Kalau lolos, file disimpan di `uploads/` dengan nama acak (`bin2hex(random_bytes(8))`) supaya tidak bentrok/tidak bisa ditebak.
4. `upload.php` balas `{url: "uploads/xxxx.jpg", caption: "..."}`.
5. Admin panel menambahkan entry itu ke array `slideImages` di memori, lalu **POST seluruh config** (termasuk daftar gambar terbaru) ke `api/config.php` — tersimpan permanen di `data/config.json`.
6. Begitu kiosk (`view1.php`) reload, ia baca `config.json` yang sudah berisi gambar baru itu dan langsung ikut diputar di slideshow.
- Reorder (naik/turun) dan hapus gambar dari list juga langsung memicu POST ke `api/config.php` — tidak ada tombol "Simpan" terpisah untuk itu.
- Menghapus gambar dari daftar **tidak menghapus file fisik** di `uploads/` (belum ada cleanup otomatis) — file lama jadi "yatim" tapi tidak berbahaya, cuma numpuk storage lama-lama. Catat sebagai item bersih-bersih di Fase 6 kalau mau dirapikan.

**Login & keamanan:**
- Login pertama kali otomatis membuat `data/admin.json` dengan password default **`admin123`** (di-hash, bukan plain text) — admin panel mengingatkan untuk segera ganti lewat tab Profil Masjid.
- Session pakai PHP native session (cookie), bukan token di localStorage — lebih aman dari XSS baca token.
- `data/` diblokir `.htaccess` supaya `config.json`/`admin.json` tidak bisa diakses langsung lewat URL (hanya bisa dibaca lewat script PHP).

---

## 5. Skema Konfigurasi (Diperbarui)

```javascript
const config = {
    activeTheme: "tipe1", // "tipe1" | "tipe2" -- dipilih dari Admin Panel, dibaca view1.php

    mosque: {
        name: "Masjid Agung Al-Ikhlas",
        address: "Jl. Utama No. 123, Cebongan, Yogyakarta",
        runningText: "Harap mematikan atau mengheningkan suara HP Anda..."
    },

    // Jadwal HARI INI -- diisi manual oleh admin (Fase 3 API belum aktif).
    // Field ini yang benar-benar dibaca & ditampilkan kiosk saat ini.
    prayerSchedule: {
        imsak: "04:18", shubuh: "04:28", terbit: "05:39",
        dzuhur: "11:49", ashar: "15:02", maghrib: "17:52", isya: "19:01"
    },

    // Sumber jadwal OTOMATIS (Fase 3, belum aktif) -- koordinat + koreksi manual (menit)
    prayerApi: {
        provider: "aladhan",
        lat: -7.7326,
        lng: 110.3591,
        method: 20, // pendekatan Kemenag RI
        correctionMinutes: { shubuh: 2, dzuhur: 1, ashar: 1, maghrib: 0, isya: 1 }
    },

    // Sub-rotasi View 1 (Mode Normal) -- detik per sub-tampilan
    normalCarousel: {
        dashboardSeconds: 30,
        photoSeconds: 30,
        analogClockSeconds: 30
    },

    // Durasi tiap fase per waktu sholat (menit), beda2 sesuai kebiasaan masjid
    timing: {
        preAdzanMinutes: 10,
        azanDurationMinutes: { shubuh: 4, dzuhur: 3, ashar: 3, maghrib: 3, isya: 3 },
        iqomahOffsetMinutes: { shubuh: 15, dzuhur: 10, ashar: 10, maghrib: 5, isya: 10 },
        sholatDurationMinutes: { shubuh: 12, dzuhur: 10, ashar: 10, maghrib: 8, isya: 12 },
        jumat: {
            khutbahDurationMinutes: 35,
            sholatDurationMinutes: 10
        }
    },

    slideImages: [
        { url: '/uploads/slide1.jpg', caption: 'Masjid Nabawi, Madinah' }
        // dikelola lewat Admin Panel, bukan hardcode
    ]
};
```

---

## 6. Komponen yang Perlu Dibangun

### Sudah ada (dari prototype, tinggal diintegrasikan ke SPA)
- View NORMAL, MENJELANG_ADZAN, ADZAN (styling & animasi sudah bagus, tinggal dipindah jadi komponen)

### Baru
1. **View MENJELANG_IQOMAH** — reuse pola ring countdown, label & target waktu beda
2. **View SHOLAT_BERLANGSUNG** — kaligrafi/ayat statis, tanpa angka, tanpa countdown
3. **State Machine** (`stateMachine.js`) — evaluasi state tiap detik berdasarkan jadwal + config timing
4. **Prayer Schedule Service** — fetch Aladhan API harian + apply `correctionMinutes` + cache offline
5. **Admin Panel** (halaman + backend API + storage gambar)
6. **Audio Service** — beep/chime dulu, dengan interface yang siap diganti file mp3 asli nanti
7. **Kiosk Watchdog** — auto-reload harian, deteksi tab freeze/jam browser drift
8. **Dev/Simulation Mode** — panel simulasi dari prototype tetap dipakai untuk testing, tapi hanya muncul kalau URL punya `?dev=1`

---

## 7. Rencana Fase Implementasi

### Fase 1 — Fondasi SPA & Refactor (2–3 hari)
- [x] ~~Gabungkan 3 prototype jadi 1 SPA dengan `viewManager.js`~~ — diselesaikan dengan pendekatan berbeda, lihat Fase 2 (iframe + state machine, bukan SPA penuh)
- [x] Pindahkan jam, tanggal, marquee jadi data yang di-drive config (masing-masing view tetap punya markup sendiri, tapi datanya seragam dari `window.__DAFI_CONFIG__` / query param)
- [ ] Sembunyikan panel simulasi di balik `?dev=1` — saat ini panel demo otomatis hilang saat dibuka lewat `view1.php` (ada query param asli), tapi masih selalu tampil kalau file dibuka standalone tanpa `?dev=1` eksplisit

### Fase 2 — State Machine & 2 View Baru (2 hari) — **SELESAI (2026-09-17)**
- [x] Bangun view **MENJELANG_IQOMAH** (View 4)
- [x] Bangun view **SHOLAT_BERLANGSUNG** (View 5, kaligrafi tenang, tanpa timer)
- [x] Implementasi state machine — **`docs/state-machine.js`**, bukan pendekatan SPA gabung-semua-jadi-satu-script seperti rencana awal. Pendekatannya:
  - `view1.php` menyisipkan `<div id="state-overlay"><iframe id="state-iframe"></iframe></div>` + `<script src="docs/state-machine.js">` sebelum `</body>`.
  - Tiap detik, `state-machine.js` hitung state seharusnya (`NORMAL` / `MENJELANG_ADZAN` / `ADZAN` / `MENJELANG_IQOMAH` / `SHOLAT`) dari `window.__DAFI_CONFIG__.prayerSchedule` + `.timing`, dibanding jam sekarang (`new Date()`).
  - Selain NORMAL, view yang sesuai (`docs/view2..5.html`) dimuat ke `<iframe>` dengan data asli lewat **query string** (`?prayer=Dzuhur&target=2026-09-17T...&mosqueName=...&mosqueAddress=...&runningText=...`) — **bukan** digabung jadi satu script, supaya variabel/fungsi tiap view (yang sebelumnya berdiri sendiri) tidak bentrok satu sama lain.
  - View 2, 3, 4 dimodifikasi supaya baca `URLSearchParams(location.search)`: kalau ada `target`, jalankan hitung mundur **sungguhan** ke waktu itu (bukan demo 60 detik lagi) & sembunyikan Panel Simulasi Demo; kalau tidak ada (dibuka standalone), tetap jalan mode demo seperti sebelumnya.
  - View 5 baca `?prayer=` untuk ganti label "Sedang Sholat [Nama] Berjamaah".
  - Saat keluar dari NORMAL, `carouselTimer` (sub-rotasi View 1) di-`clearTimeout` dan direset ke `dashboard`; saat balik ke NORMAL, `runNormalCarousel()` dipanggil lagi.
  - **Alur Jumat** (Dzuhur di hari Jumat): tidak ada fase MENJELANG_IQOMAH terpisah — begitu adzan selesai, langsung ke View 5 (kaligrafi) selama `khutbahDurationMinutes + sholatDurationMinutes` gabungan, sesuai catatan MVP di Bagian 2.
- [x] Uji transisi penuh: NORMAL → MENJELANG_ADZAN → ADZAN → MENJELANG_IQOMAH → SHOLAT → NORMAL (diuji end-to-end pakai jadwal dipercepat, semua transisi terkonfirmasi jalan otomatis tanpa reload halaman)
- [x] Format countdown View 2 & View 4 diubah dari detik mentah ke **MM:SS** (mis. `09:45`) karena durasi menjelang adzan/iqomah biasanya beberapa menit, bukan puluhan detik

### Fase 3 — Integrasi Jadwal Sholat API (1–2 hari)
- [ ] Integrasi Aladhan API berdasarkan koordinat masjid
- [ ] Terapkan `correctionMinutes` manual
- [ ] Cache harian ke `localStorage` + fallback kalau fetch gagal
- [ ] Tangani kasus khusus Jumat (Dzuhur → alur Jumatan)

### Fase 4 — Admin Panel & Backend (3–4 hari)
- [x] Backend ringan (PHP + JSON file di `data/config.json`) untuk config & upload gambar — lihat Bagian 4.1
- [x] Halaman admin: kelola slideshow (upload asli), profil masjid, tema View 1, jadwal manual harian, durasi tiap fase, ganti password
- [x] Login admin sederhana (session PHP + password ter-hash)
- [x] Kiosk (`view1.php`) baca config langsung dari `data/config.json` tiap request (belum perlu "polling" terpisah karena tiap reload otomatis ambil data terbaru)
- [ ] Fallback ke cache terakhir kalau `config.json` corrupt/hilang (saat ini fallback ke `default_config()` hardcoded di PHP, belum simpan cache terpisah)
- [ ] Cleanup file upload yang sudah tidak dipakai di `slideImages` (saat ini jadi file "yatim", tidak auto-terhapus)

### Fase 5 — Audio & Polish (1 hari)
- [ ] Rapikan beep/chime di titik-titik transisi (mulai adzan, mulai iqomah, mulai sholat)
- [ ] Siapkan interface `audio.js` supaya gampang tinggal drop-in file mp3 adzan asli nanti

### Fase 6 — Kiosk Hardening & Testing (1–2 hari)
- [ ] Fullscreen kiosk mode + watchdog auto-reload harian
- [ ] Uji di device asli (Android TV box/mini PC) resolusi 1920×1080
- [ ] Uji skenario offline (internet masjid putus saat refresh jadwal tengah malam)
- [ ] Uji drift jam sistem & tab yang idle lama

---

## 8. Panduan Deploy ke Shared Hosting & Jalankan di TV

### 8.1 Upload ke Shared Hosting (cPanel)

1. **Upload file**: lewat File Manager cPanel atau FTP (FileZilla), upload seluruh isi folder ini ke `public_html/` (atau ke subfolder/subdomain, mis. `public_html/tv/` atau subdomain `tv.namamasjid.com`).
2. **Cek versi PHP**: di cPanel → *Select PHP Version* / *MultiPHP Manager*, pastikan domain/subdomain ini pakai **PHP 8.0 ke atas** (dites pakai PHP 8.4). Backend pakai fitur PHP standar (session, `password_hash`, `finfo`), tidak butuh extension aneh-aneh.
3. **Permission folder**: pastikan folder `data/` dan `uploads/` bisa ditulis PHP (biasanya default `755` sudah cukup di shared hosting; kalau upload/simpan config gagal dengan error permission, coba `775`, hindari `777` kecuali benar-benar terpaksa).
4. **Cek `.htaccess` aktif**: buka `https://domain-anda.com/data/config.json` langsung dari browser — **harus muncul error 403 Forbidden**. Kalau malah kebuka isinya, berarti hosting pakai Nginx/LiteSpeed yang tidak baca `.htaccess` versi ini — perlu setting setara lewat panel hosting (tanyakan ke support hosting cara blokir akses folder).
5. **Login pertama**: buka `https://domain-anda.com/docs/admin_settings.html`, login pakai password default `admin123`, **langsung ganti password** lewat tab *Profil Masjid → Ganti Password*.
6. **Isi data**: profil masjid, jadwal sholat hari ini, upload gambar slideshow, pilih Tema (Tipe 1/Tipe 2) — semua dari Admin Panel, tidak perlu edit kode lagi.
7. **URL untuk kiosk**: `https://domain-anda.com/view1.php` — **bukan** `view1_normal.html` langsung (itu cuma untuk poles desain, datanya statis/demo).

### 8.2 Menjalankan di TV

TV biasa (bukan Android TV/Smart TV dengan browser lengkap) **tidak bisa** langsung buka URL — perlu 1 device tambahan yang disambungkan ke TV lewat HDMI dan menjalankan browser dalam mode kiosk (fullscreen, tanpa address bar, auto-start). Tiga opsi umum:

**Opsi A — Mini PC / PC bekas + Windows (paling gampang disetup dari yang sudah ada)**
1. Install Google Chrome.
2. Buat shortcut baru, isi *Target*-nya:
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --noerrdialogs --disable-infobars --overscroll-history-navigation=0 https://domain-anda.com/view1.php
   ```
3. Taruh shortcut itu di folder Startup (`shell:startup` di Run dialog) supaya otomatis jalan saat Windows nyala.
4. Set Windows: auto-login (tidak perlu ketik password saat boot), matikan sleep/screensaver (*Settings → Power*), matikan restart otomatis untuk Windows Update di jam masjid aktif.
5. Keluar dari kiosk mode saat perlu maintenance: `Alt+F4` (butuh keyboard fisik).

**Opsi B — Android TV Box (dipilih untuk instalasi ini)**
1. Install **Fully Kiosk Browser** dari Play Store di box-nya (kalau box tidak punya Play Store/cuma sideload APK, unduh APK-nya dari situs resmi `fully-kiosk.com`).
2. Buka app-nya, kalau diminta izin (Device Admin, Overlay/Draw over other apps, Accessibility) — **kasih semua izin itu**, itu yang dipakai buat mengunci layar supaya orang tidak bisa keluar dari kiosk secara tidak sengaja.
3. Di menu *Web Content Settings*: isi **Start URL** = `https://dafischool.sch.id/app/jamdigital/view1.php`.
4. Di menu *Device Management*: aktifkan **"Start on Boot"** (auto-jalan tiap box nyala) dan **"Keep Screen On"** (layar tidak pernah mati sendiri).
5. Di menu *Kiosk Mode Settings*: aktifkan supaya tombol Home/Back/Recent Apps dikunci (biar tidak ada yang tidak sengaja keluar ke launcher Android biasa).
6. **Fitur bonus yang langsung menjawab "restart harian" di catatan risiko #4**: Fully Kiosk punya *Scheduled Restart* / *Screen Off & On Schedule* bawaan — set restart otomatis 1x/hari (mis. jam 02:00 pagi) dari menu *Device Management → Restart App/Device Scheduled*. Ini gratis dipakai tanpa perlu setup cron/Task Scheduler terpisah.
7. Sambungkan box ke TV lewat HDMI. Kalau TV & box sama-sama support HDMI-CEC, aktifkan supaya keduanya nyala bareng saat listrik masuk; kalau tidak, pastikan minimal box-nya sendiri auto-boot begitu dapat daya (biasanya default Android TV box sudah begitu).
8. **Opsional tapi disarankan**: beli lisensi *Fully Kiosk Browser Plus* (sekali bayar, ~US$7–15) untuk hilangkan watermark/nag screen versi gratis dan buka fitur *Device Owner Mode* (lock lebih ketat) — tidak wajib, tapi lebih rapi untuk dipakai user akhir (jamaah), bukan cuma testing.

**Opsi C — Raspberry Pi (kalau sudah familiar Linux)**
- Sama seperti Opsi A tapi pakai Chromium: `chromium-browser --kiosk https://domain-anda.com/view1.php`, di-set jalan otomatis lewat `autostart` LXDE/systemd service.

**Opsi D — PWA "Install App" (alternatif kalau device cuma punya browser biasa, bukan Fully Kiosk)**

Aplikasi ini sudah dikonfigurasi sebagai **PWA** (`manifest.json` + `sw.js` + ikon di `icons/`, di-suntik `view1.php`). Dengan `"display": "fullscreen"` di manifest, membuka aplikasi dari ikon yang di-install akan **fullscreen total tanpa browser chrome sama sekali** — tanpa perlu Fully Kiosk Browser:

1. Buka `https://domain-anda.com/view1.php` di Chrome (Android/desktop).
2. Chrome akan menawarkan **"Install app"** / **"Add to Home Screen"** (lewat menu titik tiga kalau tidak muncul otomatis).
3. Setelah di-install, buka aplikasinya dari ikon di home screen (bukan dari browser) — inilah yang fullscreen otomatis.
4. Auto-reload (Bagian 0) & state machine tetap jalan sama persis seperti dibuka lewat browser biasa, karena tetap `view1.php` yang dimuat, cuma tanpa UI browser di sekitarnya.

**Catatan**: kalau nanti mau ganti desain ikon, jalankan ulang `php scripts/generate_icons.php` (butuh extension PHP GD) — script ini generate ulang `icons/icon-192.png` & `icons/icon-512.png` (siluet masjid navy+amber sederhana).

**Yang sering kelupaan di semua opsi:**
- **Internet stabil** di lokasi TV — kalau bisa pakai kabel LAN, bukan cuma WiFi, supaya tidak putus-putus (slideshow & nanti fetch API jadwal butuh internet).
- **TV auto-nyala & auto-pilih input HDMI yang benar** setelah listrik mati/nyala lagi (banyak TV komersial/hotel punya setting ini, cek menu *Signage*/*Hotel Mode* kalau ada).
- **Restart harian**: jadwalkan device restart otomatis 1x/hari (jam 2 pagi misalnya) untuk mencegah browser jadi lambat/nge-lag kalau nyala 24 jam terus-menerus — ini yang dimaksud "Kiosk Watchdog" di Fase 6, belum diimplementasi otomatis dari sisi aplikasi, jadi untuk sekarang atur manual lewat Task Scheduler (Windows) / cron (Linux/Android).

### 8.3 Cara Kelola Admin Panel Sehari-hari

**Login**: buka `https://domain-anda.com/docs/admin_settings.html` dari HP/laptop pengurus masjid (tidak perlu dari device TV-nya). Password: yang sudah diganti dari default `admin123`.

| Tab | Dipakai untuk | Kapan diisi/update |
|---|---|---|
| **Profil Masjid** | Nama masjid, alamat, teks berjalan (marquee), **pilih Tema View 1** (Tipe 1/Tipe 2), ganti password | Sekali di awal; teks berjalan bisa diubah kapan saja (mis. pengumuman kegiatan) |
| **Jadwal Sholat** | Isi 7 waktu (Imsak–Isya) untuk **hari ini** secara manual | **Rutin** — harian atau borongan sebulan sekali kalau jadwalnya dari cetakan Kemenag/kalender masjid. Bagian "Sumber Jadwal (API)" di bawahnya belum aktif, isinya boleh diabaikan dulu |
| **Durasi & Transisi View** | Berapa lama tiap sub-tampilan (Dashboard/Foto/Jam Analog), berapa menit sebelum adzan mulai countdown, berapa lama adzan/jeda iqomah/lama sholat per waktu, alur khusus Jumat | Sekali di awal, disesuaikan kebiasaan masjid setempat — jarang perlu diubah lagi setelah pas |
| **Slideshow Gambar** | Upload gambar baru, atur urutan (panah naik/turun), hapus gambar lama | Kapan saja pengurus mau ganti foto (mis. ganti musim/hari besar) |
| **Preview Config (JSON)** | Lihat mentahan data yang sedang dipakai kiosk, buat ngecek kalau ada yang aneh | Hanya kalau perlu debug |

**Yang penting diketahui:**
- Setiap klik **Simpan** langsung tersimpan ke server (`data/config.json`) — begitu kiosk **reload halaman**, perubahan langsung kelihatan.
- Kiosk **tidak auto-refresh sendiri** selama masih standby nyala terus — perubahan baru kelihatan setelah TV/box di-restart (mis. lewat jadwal *Scheduled Restart* di Fully Kiosk jam 2 pagi) atau di-reload manual dari menu Fully Kiosk Browser (ada tombol reload di notification/menu-nya).
- Kalau butuh lihat perubahan **langsung saat itu juga** (bukan nunggu restart terjadwal), buka Fully Kiosk Browser di box-nya, tarik notification bar / masuk menu settings-nya, cari tombol **"Reload Start URL"**.
- Karena jadwal sholat masih manual, **jangan lupa update tab Jadwal Sholat** tiap hari/bulan — kalau lupa, kiosk akan terus menampilkan jadwal hari sebelumnya (tidak ada peringatan otomatis untuk ini saat ini).

---

## 9. Risiko & Catatan Penting

1. **Ketergantungan internet**: jadwal via API + gambar admin-hosted → kiosk harus tetap jalan dengan data cache terakhir kalau internet masjid putus.
2. **Akurasi API vs jadwal resmi**: hasil hitung Aladhan bisa beda 1–3 menit dari jadwal Kemenag setempat — `correctionMinutes` di admin panel wajib ada, jangan andalkan API mentah-mentah.
3. **Kebijakan audio browser**: Web Audio API butuh interaksi user pertama sebelum bisa bunyi di beberapa browser — untuk kiosk yang auto-load tanpa klik, perlu trik (mis. autoplay policy override di kiosk browser flag, atau video/audio tersembunyi yang di-trigger sekali saat load).
4. **Device yang menyala 24 jam**: wajib ada watchdog reload harian, jangan andalkan `setInterval` berjalan tanpa henti berhari-hari.
5. **Alur Jumat berbeda signifikan** dari hari biasa (khutbah, durasi lebih panjang) — jangan pakai `sholatDurationMinutes` harian biasa untuk Dzuhur di hari Jumat.
6. **Keamanan admin panel**: auth password (hashed) + validasi upload (cek isi file lewat `finfo`, batas 8MB, nama file di-random) sudah ada di `api/upload.php`. **Wajib** ganti password default `admin123` segera setelah login pertama — panel sudah mengingatkan lewat form "Ganti Password", tapi tidak dipaksa (belum ada validasi "password masih default, tidak bisa lanjut").
7. **`data/` folder wajib tidak bisa diakses browser** di hosting produksi — `.htaccess` sudah disiapkan, tapi ini hanya berlaku di Apache (umum di shared hosting cPanel). Kalau ternyata hostingnya pakai Nginx/LiteSpeed, `.htaccess` tidak berlaku dan perlu konfigurasi setara secara manual — cek dulu sebelum deploy.
8. **Sinkronisasi jam server (Bagian 0) cuma benerin jam yang SALAH/DRIFT** (device lupa waktu setelah mati listrik, dsb) — **bukan** benerin **zona waktu yang salah setting**. Kalau device di-set ke zona waktu yang salah (mis. WITA padahal masjidnya di WIB), jam yang tampil & perhitungan state tetap ikut salah segitu, karena kita cuma percaya selisih epoch ke server, bukan menimpa timezone device. Tetap wajib cek *Settings → Date & Time* di device sekali di awal setup (lihat Bagian 8.2).
