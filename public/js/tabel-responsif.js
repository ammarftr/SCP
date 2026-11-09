/*
 * Tabel responsif untuk HP: di layar < 768px baris tabel tampil sebagai kartu
 * (CSS-nya ada di css/app.css, bagian "Tabel responsif di HP").
 *
 * Skrip ini hanya menambahkan kelas + atribut data-label (diambil dari teks <th>)
 * pada tiap sel, sehingga tidak perlu mengubah tiap halaman satu per satu.
 *
 * Berlaku untuk : table.table di dalam .table-responsive yang punya <thead>
 * Dikecualikan  : tambahkan kelas .tabel-tetap pada <table>
 * Baris baru    : otomatis dilabeli (MutationObserver pada <tbody>)
 */
(function () {
    'use strict';

    function teks(th) { return (th.textContent || '').replace(/\s+/g, ' ').trim(); }

    function siapkan(tabel) {
        var thead = tabel.tHead;
        if (!thead || !thead.rows.length) return;

        // Peta indeks kolom -> label (mendukung colspan pada header)
        var label = [];
        Array.from(thead.rows[thead.rows.length - 1].cells).forEach(function (th) {
            var span = th.colSpan || 1;
            var t = teks(th);
            var jenis = '';
            if (th.querySelector('input[type=checkbox]')) { jenis = 'cek'; th.classList.add('sel-cek'); tabel.classList.add('tabel-ada-massal'); }
            else if (/^aksi$/i.test(t)) jenis = 'aksi';
            for (var i = 0; i < span; i++) label.push({ t: t, jenis: jenis });
        });

        function labeli(tr) {
            if (tr.dataset.lbl === '1') return;
            var sel = Array.from(tr.children).filter(function (c) { return c.tagName === 'TD'; });
            if (sel.some(function (c) { return c.colSpan > 1; })) { tr.dataset.lbl = '1'; return; }
            sel.forEach(function (td, i) {
                var l = label[i];
                if (!l) return;
                if (l.jenis === 'aksi') td.classList.add('sel-aksi');
                else if (l.jenis === 'cek') td.classList.add('sel-cek');
                else if (l.t) td.setAttribute('data-label', l.t);
            });
            tr.dataset.lbl = '1';
        }

        function semuaBaris() {
            Array.from(tabel.tBodies).forEach(function (tb) {
                Array.from(tb.rows).forEach(labeli);
            });
        }

        semuaBaris();
        tabel.classList.add('tabel-kartu');

        Array.from(tabel.tBodies).forEach(function (tb) {
            new MutationObserver(semuaBaris).observe(tb, { childList: true });
        });
    }

    function mulai() {
        document.querySelectorAll('.table-responsive > table.table:not(.tabel-tetap)').forEach(siapkan);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
    else mulai();
})();
