/*
 * Dropdown yang bisa dicari (ketik untuk memfilter) tetapi tetap berupa
 * daftar yang bisa di-scroll. Aktif untuk <select class="select-search">.
 * Memakai Tom Select (CDN). Kalau CDN gagal dimuat, select biasa tetap berfungsi.
 */
(function () {
    'use strict';
    function init() {
        if (!window.TomSelect) return;
        document.querySelectorAll('select.select-search').forEach(function (el) {
            if (el.tomselect) return;
            var first = el.options.length ? el.options[0] : null;
            var placeholder = first && first.value === '' ? first.text : 'Pilih...';
            new TomSelect(el, {
                maxOptions: null,          // tampilkan semua opsi (bisa di-scroll)
                allowEmptyOption: true,
                create: false,
                placeholder: placeholder,
                dropdownParent: 'body',    // agar tidak terpotong di dalam modal / card
                openOnFocus: true,
                render: {
                    no_results: function () {
                        return '<div class="no-results">Tidak ditemukan</div>';
                    }
                }
            });
        });
    }
    document.addEventListener('DOMContentLoaded', init);
})();
