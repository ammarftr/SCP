/*
 * Password UI (dipakai di semua halaman):
 *  1. Tombol ikon mata untuk menampilkan / menyembunyikan password.
 *  2. Notifikasi kalau password kurang dari batas minimal (atribut minlength):
 *     - petunjuk langsung di bawah kolom saat mengetik
 *     - toast merah saat form dikirim dengan password yang terlalu pendek
 */
(function () {
    'use strict';

    function ensureStyle() {
        if (document.getElementById('pwUiStyle')) return;
        var st = document.createElement('style');
        st.id = 'pwUiStyle';
        st.textContent =
            '.pw-wrap{position:relative;}' +
            '.pw-wrap input.form-control{padding-right:2.75rem;}' +
            '.pw-eye{position:absolute;top:0;right:0;height:38px;width:2.75rem;border:0;background:transparent;color:#66707E;display:flex;align-items:center;justify-content:center;cursor:pointer;}' +
            '.pw-eye:hover{color:#1B4B7A;}' +
            '.pw-hint{font-size:.78rem;color:#dc3545;margin-top:.3rem;display:none;}' +
            '.pw-hint.show{display:block;}' +
            '.pw-toast-box{position:fixed;top:1rem;right:1rem;z-index:2000;display:flex;flex-direction:column;gap:.5rem;max-width:calc(100vw - 2rem);}' +
            '.pw-toast{background:#fff;border:1px solid #f5c2c7;border-left:5px solid #dc3545;color:#842029;padding:.75rem 1rem;border-radius:10px;box-shadow:0 6px 20px rgba(16,24,40,.15);font-size:.88rem;display:flex;gap:.6rem;align-items:flex-start;width:340px;max-width:100%;}' +
            '.pw-toast i{font-size:1.1rem;color:#dc3545;margin-top:.1rem;}';
        document.head.appendChild(st);
    }

    function toast(msg) {
        var box = document.querySelector('.pw-toast-box');
        if (!box) {
            box = document.createElement('div');
            box.className = 'pw-toast-box';
            document.body.appendChild(box);
        }
        var t = document.createElement('div');
        t.className = 'pw-toast';
        t.setAttribute('role', 'alert');
        t.innerHTML = '<i class="bi bi-exclamation-octagon-fill"></i><div></div>';
        t.lastChild.textContent = msg;
        box.appendChild(t);
        setTimeout(function () {
            t.style.transition = 'opacity .3s';
            t.style.opacity = '0';
            setTimeout(function () { t.remove(); }, 300);
        }, 4500);
    }

    function label(input) {
        var l = input.closest('.mb-3, .mb-2, div') && input.closest('.mb-3, .mb-2, div').querySelector('label');
        return l ? l.textContent.trim() : 'Kata sandi';
    }

    function setup(input) {
        if (input.dataset.pwReady) return;
        input.dataset.pwReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var eye = document.createElement('button');
        eye.type = 'button';
        eye.className = 'pw-eye';
        eye.setAttribute('aria-label', 'Tampilkan kata sandi');
        eye.innerHTML = '<i class="bi bi-eye"></i>';
        wrap.appendChild(eye);

        eye.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            eye.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
            eye.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });

        var min = parseInt(input.getAttribute('minlength'), 10);
        if (!min) return;

        var hint = document.createElement('div');
        hint.className = 'pw-hint';
        wrap.parentNode.insertBefore(hint, wrap.nextSibling);

        function check() {
            var len = input.value.length;
            if (len > 0 && len < min) {
                hint.textContent = 'Kata sandi terlalu pendek. Minimal ' + min + ' karakter (kurang ' + (min - len) + ' lagi).';
                hint.classList.add('show');
            } else {
                hint.classList.remove('show');
            }
        }
        input.addEventListener('input', check);

        // Ganti bubble bawaan browser dengan notifikasi kita sendiri
        input.addEventListener('invalid', function (e) {
            if (input.value.length > 0 && input.value.length < min) {
                e.preventDefault();
                check();
                toast(label(input) + ' terlalu pendek! Minimal ' + min + ' karakter.');
                input.focus();
            }
        });
    }

    function init(root) {
        ensureStyle();
        (root || document).querySelectorAll('input[type="password"]').forEach(setup);
    }

    document.addEventListener('DOMContentLoaded', function () { init(); });
})();
