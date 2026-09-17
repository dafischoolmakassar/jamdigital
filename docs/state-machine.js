/**
 * Master state machine kiosk -- otomatis pindah View 1 (Normal) <-> View 2..5
 * berdasarkan window.__DAFI_CONFIG__ (di-inject oleh view1.php) dibandingkan
 * dengan jam sekarang. View 2-5 ditampilkan lewat <iframe> (bukan digabung
 * jadi satu script) supaya variabel/fungsi masing-masing view tidak
 * bentrok satu sama lain -- tiap iframe adalah dokumen browser terpisah.
 *
 * File ini bergantung pada beberapa hal yang didefinisikan di
 * view1_normal.html / view1_normal_tipe2.html (berjalan di halaman yang
 * sama, bukan di dalam iframe):
 *   - runNormalCarousel()   -- melanjutkan sub-rotasi Dashboard/Foto/Jam Analog
 *   - carouselTimer         -- di-clearTimeout saat keluar dari NORMAL
 *   - showCarouselScreen()  -- dipakai utk reset ke 'dashboard' sebelum overlay ditutup
 *
 * Elemen yang wajib ada di halaman (disuntik oleh view1.php sebelum </body>):
 *   <div id="state-overlay"><iframe id="state-iframe"></iframe></div>
 */
(function () {
    const config = window.__DAFI_CONFIG__;
    if (!config) return; // dibuka standalone tanpa view1.php -- tidak ada jadwal, jangan jalankan apapun

    const PRAYER_ORDER = ['shubuh', 'dzuhur', 'ashar', 'maghrib', 'isya'];
    const PRAYER_LABELS = { shubuh: 'Shubuh', dzuhur: 'Dzuhur', ashar: 'Ashar', maghrib: 'Maghrib', isya: 'Isya' };

    // Path relatif terhadap URL kiosk (/view1.php di root), BUKAN relatif
    // terhadap lokasi file state-machine.js sendiri -- makanya perlu prefix "docs/".
    const FILE_MAP = {
        MENJELANG_ADZAN: 'docs/view2_menjelang_adzan.html',
        ADZAN: 'docs/view3_adzan.html',
        MENJELANG_IQOMAH: 'docs/view4_menjelang_iqomah.html',
        SHOLAT: 'docs/view5_sholat_berlangsung.html'
    };

    function parseTimeToday(hhmm, base) {
        const parts = String(hhmm || '00:00').split(':').map(Number);
        const d = new Date(base);
        d.setHours(parts[0] || 0, parts[1] || 0, 0, 0);
        return d;
    }

    function addMinutes(date, mins) {
        return new Date(date.getTime() + mins * 60000);
    }

    /**
     * Tentukan state kiosk saat ini berdasarkan jadwal & durasi di config.
     * Prioritas: prayer diperiksa berurutan Shubuh -> Isya, dan mengembalikan
     * state pertama yang cocok. Window antar-prayer diasumsikan tidak
     * overlap (jadwal sholat asli memang selalu punya jarak).
     */
    function computeMasterState(now) {
        const timing = config.timing || {};
        const schedule = config.prayerSchedule || {};

        for (const key of PRAYER_ORDER) {
            const timeStr = schedule[key];
            if (!timeStr) continue;

            const isJumat = key === 'dzuhur' && now.getDay() === 5; // 5 = Jumat

            const adzanStart = parseTimeToday(timeStr, now);
            const preAdzanStart = addMinutes(adzanStart, -(timing.preAdzanMinutes || 0));
            const azanDurationMin = (timing.azanDurationMinutes && timing.azanDurationMinutes[key]) || 3;
            const adzanEnd = addMinutes(adzanStart, azanDurationMin);

            let iqomahEnd, sholatEnd;
            if (isJumat) {
                const khutbahMin = (timing.jumat && timing.jumat.khutbahDurationMinutes) || 35;
                const jumatSholatMin = (timing.jumat && timing.jumat.sholatDurationMinutes) || 10;
                // Jumat: tidak ada fase "Menjelang Iqomah" terpisah -- langsung
                // masuk view Sholat Berlangsung (kaligrafi tenang) yang mencakup
                // durasi khutbah + sholat sekaligus (MVP, lihat PLANNING_MD).
                iqomahEnd = adzanEnd;
                sholatEnd = addMinutes(adzanEnd, khutbahMin + jumatSholatMin);
            } else {
                const iqomahOffsetMin = (timing.iqomahOffsetMinutes && timing.iqomahOffsetMinutes[key]) || 10;
                const sholatDurationMin = (timing.sholatDurationMinutes && timing.sholatDurationMinutes[key]) || 10;
                iqomahEnd = addMinutes(adzanEnd, iqomahOffsetMin);
                sholatEnd = addMinutes(iqomahEnd, sholatDurationMin);
            }

            if (now >= preAdzanStart && now < adzanStart) {
                return { state: 'MENJELANG_ADZAN', prayerKey: key, label: PRAYER_LABELS[key], target: adzanStart };
            }
            if (now >= adzanStart && now < adzanEnd) {
                return { state: 'ADZAN', prayerKey: key, label: PRAYER_LABELS[key], target: adzanEnd };
            }
            if (!isJumat && now >= adzanEnd && now < iqomahEnd) {
                return { state: 'MENJELANG_IQOMAH', prayerKey: key, label: PRAYER_LABELS[key], target: iqomahEnd };
            }
            if (now >= iqomahEnd && now < sholatEnd) {
                return { state: 'SHOLAT', prayerKey: key, label: PRAYER_LABELS[key], target: sholatEnd };
            }
        }

        return { state: 'NORMAL' };
    }

    let currentState = 'NORMAL';
    let currentPrayerKey = null;

    /**
     * SOFT-REFRESH BERKALA -- perlu untuk browser biasa (bukan Fully Kiosk
     * Browser yang punya fitur "Scheduled Restart" sendiri) supaya perubahan
     * dari Admin Panel (profil, jadwal, slideshow) sampai ke layar tanpa
     * perlu reload manual. SENGAJA TIDAK pakai location.reload() -- reload
     * penuh mereset fullscreen (baik dari klik manual/Fullscreen API maupun
     * kadang PWA), jadi sebagai gantinya fetch ulang config lewat AJAX lalu
     * suruh halaman update tampilannya sendiri lewat window.__DAFI_APPLY_CONFIG__
     * (didefinisikan di view1_normal.html / view1_normal_tipe2.html) --
     * dokumen TIDAK PERNAH reload/navigasi, jadi fullscreen tetap aman.
     *
     * Pengecualian: kalau activeTheme (Tipe 1 <-> Tipe 2) berubah, itu perlu
     * ganti file HTML sama sekali, jadi khusus kasus itu tetap reload penuh.
     *
     * Hanya jalan saat state NORMAL supaya tidak mengganggu Adzan/Iqomah/Sholat
     * yang sedang berjalan -- kalau pas jatuh tempo di tengah state itu,
     * ditunda otomatis sampai kembali NORMAL.
     */
    const AUTO_RELOAD_MINUTES = 2;
    let lastReloadAt = Date.now();
    let isRefreshing = false;

    async function maybeAutoReload() {
        const elapsedMs = Date.now() - lastReloadAt;
        if (elapsedMs < AUTO_RELOAD_MINUTES * 60000 || currentState !== 'NORMAL' || isRefreshing) return;

        lastReloadAt = Date.now();
        isRefreshing = true;
        try {
            const res = await fetch('api/config.php', { cache: 'no-store' });
            const fresh = await res.json();

            if ((fresh.activeTheme || 'tipe1') !== (config.activeTheme || 'tipe1')) {
                // Tema berubah -- butuh ganti file HTML (Tipe 1 <-> Tipe 2), tidak bisa tanpa reload penuh.
                location.reload();
                return;
            }

            Object.assign(config, fresh);
            if (typeof window.__DAFI_APPLY_CONFIG__ === 'function') {
                window.__DAFI_APPLY_CONFIG__(fresh);
            }
        } catch (e) {
            // Gagal fetch (mis. koneksi masjid putus sebentar) -- diamkan, coba lagi di siklus berikutnya
        } finally {
            isRefreshing = false;
        }
    }

    function applyState(result) {
        const overlay = document.getElementById('state-overlay');
        const iframe = document.getElementById('state-iframe');
        if (!overlay || !iframe) return;

        if (result.state === 'NORMAL') {
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-100');
            iframe.src = 'about:blank';
            if (typeof runNormalCarousel === 'function') runNormalCarousel();
            return;
        }

        // Keluar dari NORMAL: hentikan sub-rotasi View 1 & reset ke dashboard
        // supaya begitu balik ke NORMAL nanti, tidak nyangkut di sub-screen foto/jam.
        if (typeof carouselTimer !== 'undefined') clearTimeout(carouselTimer);
        if (typeof showCarouselScreen === 'function') showCarouselScreen('dashboard');

        const mosque = config.mosque || {};
        const params = new URLSearchParams({
            prayer: result.label || '',
            target: result.target.toISOString(),
            mosqueName: mosque.name || '',
            mosqueAddress: mosque.address || '',
            runningText: mosque.runningText || ''
        });

        iframe.src = FILE_MAP[result.state] + '?' + params.toString();
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100');
    }

    function tick() {
        const now = new Date();
        const result = computeMasterState(now);
        if (result.state !== currentState || (result.prayerKey || null) !== currentPrayerKey) {
            currentState = result.state;
            currentPrayerKey = result.prayerKey || null;
            applyState(result);
        }
        maybeAutoReload();
    }

    window.addEventListener('load', function () {
        tick();
        setInterval(tick, 1000);
    });
})();
