/*
 * Aksi massal pada tabel: centang baris, hapus terpilih, ubah terpilih.
 * Pembungkus  : .massal  (data-form-hapus="#id", data-modal-ubah="#id", data-nama="akun")
 * Baris       : tr.massal-baris  dengan  input.massal-cek[value]
 * Pilih semua : input.massal-semua  (hanya baris yang sedang tampil: ikut hasil pencarian/filter dan halaman aktif)
 * Pilihan tidak hilang saat mencari/memfilter/pindah halaman; jumlahnya ditampilkan di .massal-jumlah.
 * Ubah terpisah: tambahkan data-ubah-fungsi="namaFungsi" pada .massal -> window.namaFungsi(ids, wrapper) dipanggil
 *               menggantikan modal ubah bersama.
 * Filter      : select.massal-filter[data-atribut="kelas"]  mencocokkan  tr[data-kelas]
 */
(function () {
    'use strict';

    function tampil(r) { return r.style.display !== 'none' && !r.classList.contains('d-none') && !r.classList.contains('pg-sembunyi'); }
    function baris(w) { return Array.from(w.querySelectorAll('tbody tr.massal-baris')); }
    // Pilihan TETAP tersimpan walau barisnya sedang tersembunyi (hasil pencarian, filter, atau halaman lain)
    function terpilih(w) {
        return baris(w).filter(function (r) {
            var c = r.querySelector('.massal-cek');
            return c && c.checked;
        }).map(function (r) { return r.querySelector('.massal-cek').value; });
    }

    function hitung(w) {
        var n = terpilih(w).length;
        var semua = baris(w).filter(tampil);
        var nTampak = semua.filter(function (r) { var c = r.querySelector('.massal-cek'); return c && c.checked; }).length;
        var tersembunyi = n - nTampak;
        var el = w.querySelector('.massal-jumlah');
        if (el) el.textContent = n ? n + ' dipilih' + (tersembunyi ? ' (' + tersembunyi + ' tidak terlihat)' : '') : '';
        w.querySelectorAll('.massal-tombol').forEach(function (b) { b.disabled = n === 0; });
        var h = w.querySelector('.massal-semua');
        if (h) {
            h.checked = nTampak > 0 && nTampak === semua.length;
            h.indeterminate = nTampak > 0 && nTampak < semua.length;
        }
    }

    function filterBaris(w) {
        var fs = Array.from(w.querySelectorAll('.massal-filter'));
        baris(w).forEach(function (r) {
            var sembunyi = fs.some(function (f) {
                return f.value !== '' && (r.dataset[f.dataset.atribut] || '') !== f.value;
            });
            r.classList.toggle('massal-sembunyi', sembunyi);
            r.style.display = sembunyi ? 'none' : '';
        });
        hitung(w);
    }

    function isiId(form, ids) {
        var wadah = form.querySelector('.massal-ids');
        wadah.innerHTML = '';
        ids.forEach(function (id) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = 'ids[]'; i.value = id;
            wadah.appendChild(i);
        });
    }

    document.addEventListener('change', function (e) {
        var w = e.target.closest && e.target.closest('.massal');
        if (!w) return;
        if (e.target.classList.contains('massal-semua')) {
            baris(w).forEach(function (r) {
                var c = r.querySelector('.massal-cek');
                if (c && tampil(r)) c.checked = e.target.checked;   // hanya baris yang terlihat; pilihan lain dibiarkan
            });
        }
        if (e.target.classList.contains('massal-filter')) { filterBaris(w); return; }
        hitung(w);
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.massal-tombol');
        if (!b) return;
        var w = b.closest('.massal');
        var ids = terpilih(w);
        if (!ids.length) return;

        if (b.dataset.aksi === 'hapus') {
            var form = document.querySelector(w.dataset.formHapus);
            isiId(form, ids);
            form.setAttribute('data-confirm', 'Hapus ' + ids.length + ' ' + (w.dataset.nama || 'data') + ' terpilih?' + (w.dataset.peringatan ? ' ' + w.dataset.peringatan : ''));
            form.requestSubmit();
        } else if (w.dataset.ubahFungsi && typeof window[w.dataset.ubahFungsi] === 'function') {
            // Ubah terpisah: tiap data punya form sendiri (ditangani fungsi milik halaman)
            window[w.dataset.ubahFungsi](ids, w);
        } else {
            var modalEl = document.querySelector(w.dataset.modalUbah);
            modalEl.querySelector('form').reset();
            isiId(modalEl.querySelector('form'), ids);
            modalEl.querySelector('.massal-n').textContent = ids.length;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });

    // Dipanggil dari pencarian di halaman (baris disembunyikan lewat style.display)
    window.massalSegarkan = function () {
        document.querySelectorAll('.massal').forEach(function (w) {
            hitung(w);
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.massal').forEach(hitung);
    });
})();
