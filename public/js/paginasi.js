/*
 * Paginasi sisi-klien untuk tabel yang barisnya sudah dimuat semua di halaman
 * (dipakai di tempat yang sudah punya pencarian / filter / tab berbasis JS).
 *
 * Pakai   : <table data-paginasi="15"> ... </table>      (angka = baris per halaman)
 * Baris   : semua tbody tr, kecuali yang punya td[colspan] (baris "kosong") atau data-pg-lewati.
 * Filter  : baris yang disembunyikan skrip lain (style.display = 'none' atau kelas d-none)
 *           tidak dihitung; paginasi otomatis menyesuaikan dan kembali ke halaman 1.
 * Sembunyi: baris di luar halaman aktif diberi kelas .pg-sembunyi (bukan style.display),
 *           jadi tidak bentrok dengan pencarian/filter yang sudah ada.
 */
(function () {
    'use strict';

    function bisaDihitung(tr) {
        if (tr.hasAttribute('data-pg-lewati') || tr.querySelector(':scope > td[colspan]')) return false;
        return tr.style.display !== 'none' && !tr.classList.contains('d-none');
    }

    function nomorTampil(sekarang, total) {
        var set = {};
        [1, total, sekarang - 1, sekarang, sekarang + 1].forEach(function (n) {
            if (n >= 1 && n <= total) set[n] = true;
        });
        var arr = Object.keys(set).map(Number).sort(function (a, b) { return a - b; });
        var hasil = [];
        arr.forEach(function (n, i) {
            if (i > 0 && n - arr[i - 1] > 1) hasil.push('…');
            hasil.push(n);
        });
        return hasil;
    }

    function pasang(tabel) {
        var ukuran = parseInt(tabel.dataset.paginasi, 10) || 15;
        var tbody = tabel.tBodies[0];
        if (!tbody) return;

        var halaman = 1;
        var tandaTerakhir = null;

        var kontrol = document.createElement('div');
        kontrol.className = 'scp-paginasi d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 px-3 px-md-0 py-3';
        var info = document.createElement('small');
        info.className = 'text-muted';
        var nav = document.createElement('nav');
        nav.setAttribute('aria-label', 'Navigasi halaman');
        var ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm scp-pagination mb-0 flex-wrap justify-content-center';
        nav.appendChild(ul);
        kontrol.appendChild(info);
        kontrol.appendChild(nav);

        var pembungkus = tabel.closest('.table-responsive') || tabel;
        pembungkus.parentNode.insertBefore(kontrol, pembungkus.nextSibling);

        function butir(label, tujuan, opsi) {
            var li = document.createElement('li');
            li.className = 'page-item' + (opsi.aktif ? ' active' : '') + (opsi.mati ? ' disabled' : '');
            if (opsi.aktif) li.setAttribute('aria-current', 'page');
            var b = document.createElement(opsi.mati || opsi.aktif ? 'span' : 'button');
            b.className = 'page-link';
            b.innerHTML = label;
            if (opsi.nama) b.setAttribute('aria-label', opsi.nama);
            if (!opsi.mati && !opsi.aktif) {
                b.type = 'button';
                b.addEventListener('click', function () { pindah(tujuan, true); });
            }
            li.appendChild(b);
            return li;
        }

        function gambar(jumlahHalaman, total, awal, akhir) {
            ul.innerHTML = '';
            kontrol.classList.toggle('d-none', total <= ukuran);
            if (total === 0) return;
            info.textContent = 'Menampilkan ' + (awal + 1) + '–' + akhir + ' dari ' + total + ' data';
            ul.appendChild(butir('&lsaquo;', halaman - 1, { mati: halaman === 1, nama: 'Halaman sebelumnya' }));
            nomorTampil(halaman, jumlahHalaman).forEach(function (n) {
                if (n === '…') ul.appendChild(butir('…', 0, { mati: true }));
                else ul.appendChild(butir(String(n), n, { aktif: n === halaman }));
            });
            ul.appendChild(butir('&rsaquo;', halaman + 1, { mati: halaman === jumlahHalaman, nama: 'Halaman berikutnya' }));
        }

        function render(paksaHalaman1) {
            var semua = Array.from(tbody.querySelectorAll(':scope > tr'));
            var cocok = semua.filter(bisaDihitung);
            var tanda = cocok.length + ':' + (cocok[0] ? semua.indexOf(cocok[0]) : '-') + ':' + (cocok.length ? semua.indexOf(cocok[cocok.length - 1]) : '-');

            // Himpunan baris berubah (cari / filter / ganti tab): mulai dari halaman 1
            if (paksaHalaman1 || (tandaTerakhir !== null && tanda !== tandaTerakhir)) halaman = 1;
            tandaTerakhir = tanda;

            var jumlahHalaman = Math.max(1, Math.ceil(cocok.length / ukuran));
            if (halaman > jumlahHalaman) halaman = jumlahHalaman;
            var awal = (halaman - 1) * ukuran;
            var akhir = Math.min(awal + ukuran, cocok.length);

            semua.forEach(function (tr) {
                var idx = cocok.indexOf(tr);
                tr.classList.toggle('pg-sembunyi', idx !== -1 && (idx < awal || idx >= akhir));
            });

            gambar(jumlahHalaman, cocok.length, awal, akhir);
            if (window.massalSegarkan) window.massalSegarkan(); // hilangkan centang pada baris yang tak tampil
            observer.takeRecords();                            // abaikan perubahan kelas milik kita sendiri
        }

        function pindah(n, gulir) {
            halaman = n;
            render(false);
            if (gulir) {
                var kartu = tabel.closest('.scp-card') || tabel;
                if (kartu.getBoundingClientRect().top < 0) kartu.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Pantau perubahan dari skrip lain (pencarian, filter, tab blok)
        var observer = new MutationObserver(function () { render(false); });
        observer.observe(tbody, { subtree: true, attributes: true, attributeFilter: ['style', 'class'] });

        render(true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('table[data-paginasi]').forEach(pasang);
    });
})();
