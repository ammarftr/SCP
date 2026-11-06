/*
 * Komponen "Tambah bukti": tombol plus -> pilih "Pilih File" atau "Foto Langsung (kamera)".
 * Markup: <div data-bukti> berisi input file utama (.bukti-input) + input cadangan kamera
 * (data-bukti-capture). Input utama tetap yang dikirim ke server, jadi validasi server tidak berubah.
 * Kamera memakai camera-capture.js (window.SCPKamera); kalau kamera browser tidak tersedia
 * (mis. akses lewat HTTP), otomatis pakai kamera bawaan HP lewat input capture.
 */
(function () {
    'use strict';
    var MAKS = 5 * 1024 * 1024; // 5 MB, sama dengan batas di server

    function ukuran(b) {
        if (b < 1024) return b + ' B';
        if (b < 1024 * 1024) return (b / 1024).toFixed(0) + ' KB';
        return (b / 1024 / 1024).toFixed(1) + ' MB';
    }

    function pasang(root) {
        var input = root.querySelector('.bukti-input');
        var capture = root.querySelector('[data-bukti-capture]');
        var kosong = root.querySelector('[data-bukti-empty]');
        var chip = root.querySelector('[data-bukti-chip]');
        var err = root.querySelector('[data-bukti-error]');
        var thumb = chip.querySelector('[data-bukti-thumb]');
        var nama = chip.querySelector('[data-bukti-name]');
        var info = chip.querySelector('[data-bukti-info]');
        var url = null;
        var hanyaFoto = root.dataset.buktiTipe === 'gambar';
        var hanyaKamera = input.hasAttribute('data-camera-only');

        // Mode wajib kamera: input file utama tidak boleh dibuka (tidak ada jalan ke galeri)
        if (hanyaKamera) {
            input.addEventListener('click', function (e) { e.preventDefault(); });
        }

        function pesan(t) {
            err.textContent = t || '';
            err.classList.toggle('d-none', !t);
        }

        function hapusFile() {
            input.value = '';
            if (capture) capture.value = '';
        }

        function render() {
            if (url) { URL.revokeObjectURL(url); url = null; }
            var f = input.files && input.files[0];
            kosong.classList.toggle('d-none', !!f);
            chip.classList.toggle('d-none', !f);
            if (!f) return;

            thumb.innerHTML = '';
            if (/^image\//.test(f.type)) {
                url = URL.createObjectURL(f);
                var img = document.createElement('img');
                img.src = url;
                img.alt = 'Pratinjau bukti';
                thumb.appendChild(img);
            } else {
                var ic = document.createElement('i');
                ic.className = 'bi bi-file-earmark-pdf';
                thumb.appendChild(ic);
            }
            nama.textContent = f.name;
            info.textContent = (/^image\//.test(f.type) ? 'Foto' : 'PDF') + ' \u00b7 ' + ukuran(f.size);
        }

        function periksa() {
            var f = input.files && input.files[0];
            if (!f) return true;
            var gambar = /^image\//.test(f.type);
            var pdf = f.type === 'application/pdf' || /\.pdf$/i.test(f.name);
            if (!gambar && (hanyaFoto || !pdf)) {
                hapusFile();
                pesan(hanyaFoto ? 'Format tidak didukung. Pilih foto (JPG/PNG).' : 'Format tidak didukung. Pilih PDF atau foto (JPG/PNG).');
                return false;
            }
            if (f.size > MAKS) {
                hapusFile();
                pesan('Ukuran file melebihi 5 MB. Pilih file yang lebih kecil.');
                return false;
            }
            return true;
        }

        input.addEventListener('change', function () {
            pesan('');
            periksa();
            render();
        });
        // Setelah foto selesai dikecilkan otomatis, ukuran di pratinjau ikut diperbarui
        input.addEventListener('scp:ready', function () {
            periksa();
            render();
        });

        root.querySelector('[data-bukti-remove]').addEventListener('click', function () {
            pesan('');
            hapusFile();
            render();
        });

        var tombolFile = root.querySelector('[data-aksi="file"]');
        if (tombolFile && !hanyaKamera) {
            tombolFile.addEventListener('click', function () {
                input.click();
            });
        }

        root.querySelectorAll('[data-aksi="kamera"]').forEach(function (tombol) { tombol.addEventListener('click', function () {
            var bisaWebcam = window.isSecureContext && navigator.mediaDevices
                && navigator.mediaDevices.getUserMedia && window.SCPKamera;
            if (bisaWebcam) {
                window.SCPKamera.buka(input);
            } else if (capture) {
                capture.click();
            } else {
                pesan('Kamera tidak tersedia di perangkat ini.' + (hanyaKamera ? '' : ' Gunakan "Pilih File".'));
            }
        }); });

        // Foto dari kamera bawaan HP (jalur cadangan) disalin ke input utama
        if (capture) {
            capture.addEventListener('change', function () {
                var f = capture.files && capture.files[0];
                if (!f || !window.DataTransfer) return;
                var dt = new DataTransfer();
                dt.items.add(f);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                capture.value = '';
            });
        }

        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-bukti]').forEach(pasang);
    });
})();
