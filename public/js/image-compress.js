/*
 * Mengecilkan foto otomatis di HP sebelum dikirim (hemat kuota dan lolos batas ukuran).
 * Aktif untuk <input type="file" data-compress>. File non-gambar (misalnya PDF) tidak diubah.
 */
(function () {
    'use strict';
    var SISI_MAKS = 1600;   // piksel pada sisi terpanjang
    var KUALITAS = 0.8;

    function kecilkan(file) {
        return new Promise(function (selesai) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                var skala = Math.min(1, SISI_MAKS / Math.max(img.naturalWidth, img.naturalHeight));
                var c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * skala);
                c.height = Math.round(img.naturalHeight * skala);
                var ctx = c.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, c.width, c.height);
                ctx.drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(function (blob) {
                    if (!blob || (skala === 1 && blob.size >= file.size)) return selesai(file);
                    var nama = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    selesai(new File([blob], nama, { type: 'image/jpeg', lastModified: Date.now() }));
                }, 'image/jpeg', KUALITAS);
            };
            img.onerror = function () { URL.revokeObjectURL(url); selesai(file); };
            img.src = url;
        });
    }

    function kunciTombol(form, kunci) {
        if (!form) return;
        form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (b) {
            b.disabled = kunci;
        });
    }

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('input[type=file][data-compress]')) return;
        var file = input.files && input.files[0];
        if (!file || !/^image\//.test(file.type) || !window.DataTransfer) return;

        kunciTombol(input.form, true);
        kecilkan(file).then(function (baru) {
            if (baru !== file) {
                var dt = new DataTransfer();
                dt.items.add(baru);
                input.files = dt.files;
            }
        }).catch(function () {}).then(function () {
            kunciTombol(input.form, false);
            // Beri tahu komponen lain (mis. pratinjau) bahwa file sudah selesai dikecilkan
            input.dispatchEvent(new CustomEvent('scp:ready', { bubbles: true }));
        });
    });
})();
