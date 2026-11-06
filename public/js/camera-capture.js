/*
 * Ambil foto langsung dari kamera (realtime) untuk <input type="file" data-camera>.
 * Input file aslinya tetap dipakai, jadi pengguna bebas memilih: ambil lewat kamera
 * ATAU pilih file dari galeri/penyimpanan. Foto hasil kamera dimasukkan ke input yang sama
 * dan memicu event "change", sehingga image-compress.js dan validasi server tetap berjalan.
 * Catatan: kamera di browser hanya jalan lewat HTTPS (atau localhost).
 */
(function () {
    'use strict';
    var SISI_MAKS = 1600;
    var KUALITAS = 0.85;

    var modalEl, modal, video, hasilImg, pesan, btnAmbil, btnGanti, btnUlang, btnPakai;
    var stream = null, inputAktif = null, hadap = 'environment', hasilBlob = null, hasilUrl = null;

    function buatModal() {
        if (modalEl) return;
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">' +
              '<div class="modal-content">' +
                '<div class="modal-header py-2">' +
                  '<h6 class="modal-title mb-0"><i class="bi bi-camera me-2"></i>Ambil Foto</h6>' +
                  '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>' +
                '</div>' +
                '<div class="modal-body p-2">' +
                  '<div class="alert alert-warning small d-none mb-2" data-k="pesan"></div>' +
                  '<div class="rounded overflow-hidden bg-dark text-center">' +
                    '<video data-k="video" playsinline muted autoplay style="width:100%;max-height:70vh;display:block;"></video>' +
                    '<img data-k="hasil" alt="Hasil foto" class="d-none" style="width:100%;max-height:70vh;object-fit:contain;">' +
                  '</div>' +
                '</div>' +
                '<div class="modal-footer py-2 justify-content-between">' +
                  '<button type="button" class="btn btn-outline-secondary btn-sm" data-k="ganti"><i class="bi bi-arrow-repeat me-1"></i>Ganti Kamera</button>' +
                  '<div class="d-flex gap-2">' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm d-none" data-k="ulang">Ulangi</button>' +
                    '<button type="button" class="btn btn-primary btn-sm d-none" data-k="pakai"><i class="bi bi-check2 me-1"></i>Pakai Foto</button>' +
                    '<button type="button" class="btn btn-primary btn-sm" data-k="ambil"><i class="bi bi-camera-fill me-1"></i>Ambil</button>' +
                  '</div>' +
                '</div>' +
              '</div>' +
            '</div>';
        document.body.appendChild(modalEl);

        function q(k) { return modalEl.querySelector('[data-k="' + k + '"]'); }
        video = q('video'); hasilImg = q('hasil'); pesan = q('pesan');
        btnAmbil = q('ambil'); btnGanti = q('ganti'); btnUlang = q('ulang'); btnPakai = q('pakai');

        btnAmbil.addEventListener('click', ambil);
        btnUlang.addEventListener('click', ulangi);
        btnPakai.addEventListener('click', pakai);
        btnGanti.addEventListener('click', function () {
            hadap = hadap === 'environment' ? 'user' : 'environment';
            mulai();
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            matikan();
            bersihHasil();
        });
        modal = new bootstrap.Modal(modalEl);
    }

    function tampilPesan(teks) {
        pesan.textContent = teks;
        pesan.classList.toggle('d-none', !teks);
    }

    function matikan() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    function bersihHasil() {
        if (hasilUrl) { URL.revokeObjectURL(hasilUrl); hasilUrl = null; }
        hasilBlob = null;
        if (!hasilImg) return;
        hasilImg.classList.add('d-none');
        hasilImg.removeAttribute('src');
        video.classList.remove('d-none');
        btnUlang.classList.add('d-none');
        btnPakai.classList.add('d-none');
        btnAmbil.classList.remove('d-none');
        btnGanti.classList.remove('d-none');
    }

    function saranFile() {
        return (inputAktif && inputAktif.hasAttribute('data-camera-only')) ? '' : ' Gunakan "Pilih file" sebagai gantinya.';
    }

    function mulai() {
        matikan();
        tampilPesan('');
        if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            tampilPesan('Kamera tidak bisa dipakai di alamat ini (perlu HTTPS atau localhost).' + saranFile());
            btnAmbil.disabled = true;
            return;
        }
        btnAmbil.disabled = false;
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: hadap }, width: { ideal: 1920 }, height: { ideal: 1080 } },
            audio: false
        }).then(function (s) {
            stream = s;
            video.srcObject = s;
            return video.play();
        }).catch(function (err) {
            btnAmbil.disabled = true;
            if (err && err.name === 'NotAllowedError') {
                tampilPesan('Izin kamera ditolak. Izinkan akses kamera di pengaturan browser.' + saranFile());
            } else if (err && err.name === 'NotFoundError') {
                tampilPesan('Kamera tidak ditemukan di perangkat ini.' + saranFile());
            } else {
                tampilPesan('Kamera tidak bisa dibuka (mungkin sedang dipakai aplikasi lain).' + saranFile());
            }
        });
    }

    function ambil() {
        if (!stream || !video.videoWidth) return;
        var skala = Math.min(1, SISI_MAKS / Math.max(video.videoWidth, video.videoHeight));
        var c = document.createElement('canvas');
        c.width = Math.round(video.videoWidth * skala);
        c.height = Math.round(video.videoHeight * skala);
        c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
            if (!blob) { tampilPesan('Gagal mengambil foto, coba lagi.'); return; }
            hasilBlob = blob;
            hasilUrl = URL.createObjectURL(blob);
            hasilImg.src = hasilUrl;
            hasilImg.classList.remove('d-none');
            video.classList.add('d-none');
            matikan(); // lepas kamera selama meninjau hasil
            btnAmbil.classList.add('d-none');
            btnGanti.classList.add('d-none');
            btnUlang.classList.remove('d-none');
            btnPakai.classList.remove('d-none');
        }, 'image/jpeg', KUALITAS);
    }

    function ulangi() {
        bersihHasil();
        mulai();
    }

    function pakai() {
        if (!hasilBlob || !inputAktif) return;
        var awalan = (inputAktif.dataset && inputAktif.dataset.cameraPrefix) || 'jurnal';
        var file = new File([hasilBlob], awalan + '_' + Date.now() + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        var dt = new DataTransfer();
        dt.items.add(file);
        inputAktif.files = dt.files;
        inputAktif.dispatchEvent(new Event('change', { bubbles: true }));
        modal.hide();
    }

    function bukaKamera(input) {
        buatModal();
        inputAktif = input;
        bersihHasil();
        modal.show();
        mulai();
    }

    // Pratinjau kecil foto terpilih (dari kamera maupun dari file)
    function pasangPratinjau(input, kotak) {
        var url = null;
        input.addEventListener('change', function () {
            if (url) { URL.revokeObjectURL(url); url = null; }
            var f = input.files && input.files[0];
            if (!f || !/^image\//.test(f.type)) { kotak.innerHTML = ''; return; }
            url = URL.createObjectURL(f);
            kotak.innerHTML = '<img src="' + url + '" alt="Pratinjau foto" class="rounded border" style="max-height:120px;max-width:100%;">';
        });
    }

    // Dipakai komponen lain yang punya tombol kamera sendiri
    window.SCPKamera = { buka: bukaKamera };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type=file][data-camera]').forEach(function (input) {
            // data-camera="manual": tombol kamera dibuat sendiri oleh komponen lain (lihat bukti-upload.js)
            if (input.dataset.camera === 'manual') return;
            var bar = document.createElement('div');
            bar.className = 'mb-2';
            var tombol = document.createElement('button');
            tombol.type = 'button';
            tombol.className = 'btn btn-outline-primary btn-sm';
            tombol.innerHTML = '<i class="bi bi-camera me-1"></i>Ambil Foto dengan Kamera';
            tombol.addEventListener('click', function () { bukaKamera(input); });
            bar.appendChild(tombol);
            input.parentNode.insertBefore(bar, input);

            var kotak = document.createElement('div');
            kotak.className = 'mt-2';
            input.parentNode.insertBefore(kotak, input.nextSibling);
            pasangPratinjau(input, kotak);
        });
    });
})();
