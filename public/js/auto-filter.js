/**
 * Filter otomatis.
 * Form GET yang diberi atribut data-auto-filter langsung diterapkan tanpa tombol:
 *  - pilihan dropdown / tanggal / centang berubah  -> langsung kirim
 *  - mengetik di kolom cari                        -> kirim setelah berhenti mengetik (0,6 detik)
 *  - tekan Enter di kolom cari                     -> kirim saat itu juga
 * Setelah halaman dimuat ulang, posisi gulir dan kursor di kolom cari dipulihkan,
 * jadi layar tidak melompat ke atas dan pengguna bisa lanjut mengetik.
 */
(function () {
    var KUNCI = 'scp-auto-filter';
    var TUNDA_KETIK = 600;

    // --- pulihkan posisi setelah filter menjalankan muat ulang ---
    try {
        var simpan = JSON.parse(sessionStorage.getItem(KUNCI) || 'null');
        sessionStorage.removeItem(KUNCI);
        if (simpan && simpan.path === location.pathname) {
            document.addEventListener('DOMContentLoaded', function () {
                window.scrollTo(0, simpan.y || 0);
                if (!simpan.field) return;
                var el = document.querySelector('form[data-auto-filter] [name="' + simpan.field + '"]');
                if (el && el.type !== 'hidden') {
                    el.focus();
                    try { el.setSelectionRange(el.value.length, el.value.length); } catch (e) {}
                }
            });
        }
    } catch (e) { /* penyimpanan browser dimatikan: abaikan, filter tetap jalan */ }

    function kirim(form, namaKolom) {
        if (form.dataset.sedangKirim) return;
        form.dataset.sedangKirim = '1';
        try {
            sessionStorage.setItem(KUNCI, JSON.stringify({
                path: location.pathname, y: window.scrollY, field: namaKolom || null
            }));
        } catch (e) {}
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    }

    function pasang(form) {
        var timer = null;

        form.querySelectorAll('select, input[type="date"], input[type="checkbox"], input[type="radio"]').forEach(function (el) {
            el.addEventListener('change', function () { kirim(form, null); });
        });

        form.querySelectorAll('input[type="text"], input[type="search"]').forEach(function (el) {
            // Lewati kolom ketik milik dropdown yang bisa dicari (Tom Select): mengetik di sana
            // hanya untuk menyaring daftar pilihan, bukan memicu kirim form (kalau dikirim,
            // halaman dimuat ulang dan dropdown tertutup sendiri).
            if (el.closest('.ts-wrapper') || el.closest('.ts-dropdown') || !el.name) return;
            el.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { kirim(form, el.name); }, TUNDA_KETIK);
            });
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(timer);
                    kirim(form, el.name);
                }
            });
        });

        form.addEventListener('submit', function () { clearTimeout(timer); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-auto-filter]').forEach(pasang);
    });
})();
