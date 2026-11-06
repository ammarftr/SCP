/*
 * Dialog konfirmasi berbentuk modal (pengganti confirm() bawaan browser).
 * Pakai atribut data-confirm="Teks pertanyaan" pada <form>.
 * Teks yang diawali "Hapus" memakai tombol merah.
 */
(function () {
    'use strict';
    var modalEl, modal, textEl, okBtn, pending = null;

    function build() {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-sm">' +
              '<div class="modal-content">' +
                '<div class="modal-header border-0 pb-0">' +
                  '<h6 class="modal-title fw-bold">Konfirmasi</h6>' +
                  '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>' +
                '</div>' +
                '<div class="modal-body"><p class="mb-0 confirm-text"></p></div>' +
                '<div class="modal-footer border-0 pt-0">' +
                  '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>' +
                  '<button type="button" class="btn btn-sm confirm-ok">Ya, lanjutkan</button>' +
                '</div>' +
              '</div>' +
            '</div>';
        document.body.appendChild(modalEl);
        textEl = modalEl.querySelector('.confirm-text');
        okBtn = modalEl.querySelector('.confirm-ok');
        modal = new bootstrap.Modal(modalEl);
        okBtn.addEventListener('click', function () {
            var form = pending;
            pending = null;
            modal.hide();
            if (form) { form.dataset.confirmed = '1'; form.submit(); }
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute && form.getAttribute('data-confirm');
        if (!msg || form.dataset.confirmed === '1') return;
        e.preventDefault();
        if (!window.bootstrap) {            // cadangan bila Bootstrap gagal dimuat
            if (window.confirm(msg)) { form.dataset.confirmed = '1'; form.submit(); }
            return;
        }
        if (!modal) build();
        pending = form;
        textEl.textContent = msg;
        okBtn.className = 'btn btn-sm confirm-ok ' + (/^hapus/i.test(msg) ? 'btn-danger' : 'btn-scp-primary');
        modal.show();
    }, true);
})();
