@extends('layouts.app')

@section('title', 'Jadwal Pelajaran')

@section('content')
<div class="row g-3">
    <!-- Kolom Kiri: Form Input Jadwal -->
    <div class="col-lg-5">
        <div class="scp-card">
            <div class="scp-card-header accent-primary">
                Input Jadwal
            </div>
            <div class="scp-card-body">
                <form action="{{ route('jadwal.store') }}" method="POST" id="formInputJadwal">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kelas</label>
                        <select name="id_kelas" class="form-select select-search" required>
                            <option value="">-- Pilih Kelas --</option>
                            @foreach($kelas as $k)
                                <option value="{{ $k->id_kelas }}" {{ old('id_kelas') == $k->id_kelas ? 'selected' : '' }}>
                                    {{ $k->id_kelas }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mata Pelajaran</label>
                        <select name="id_mapel" class="form-select select-search" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            @foreach($mapels as $m)
                                <option value="{{ $m->id_mapel }}" {{ old('id_mapel') == $m->id_mapel ? 'selected' : '' }}>
                                    {{ $m->id_mapel }} &mdash; {{ $m->nama_mapel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Guru</label>
                        @if(isset($guruLogin) && $guruLogin)
                            <input type="hidden" name="id_guru" value="{{ $guruLogin->id_guru }}">
                            <input type="text" class="form-control bg-light text-dark fw-bold" value="{{ $guruLogin->nama_guru }}" readonly>
                        @else
                            <input type="text" class="form-control bg-light text-danger" value="Akun belum terhubung ke data guru" readonly>
                            <small class="text-danger">Hubungi Waka Kurikulum.</small>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Ruangan</label>
                        <select name="id_ruangan_default" class="form-select select-search" required>
                            <option value="">-- Pilih Ruangan --</option>
                            @foreach($ruangans as $r)
                                <option value="{{ $r->id_ruangan }}" {{ old('id_ruangan_default') == $r->id_ruangan ? 'selected' : '' }}>
                                    {{ $r->nama_ruangan ?? 'Ruang '.$r->id_ruangan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Hari</label>
                            <select name="hari" class="form-select" required>
                                <option value="">-- Pilih Hari --</option>
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Blok</label>
                            @php
                                $lockMap = ['Blok 1' => (bool) ($config->is_locked_blok_1 ?? false), 'Blok 2' => (bool) ($config->is_locked_blok_2 ?? false)];
                                $aktif = $config->active_cycle ?? 'Blok 1';
                                // Blok pada form mengikuti blok yang sedang tampil di Hasil
                                $default = $blokTampil;
                                if ($lockMap[$default] ?? false) {
                                    $default = collect($lockMap)->search(false) ?: $default;
                                }
                            @endphp
                            <select name="cycle_type" id="inputBlok" class="form-select" required>
                                @foreach($lockMap as $nm => $lk)
                                    <option value="{{ $nm }}" {{ $default == $nm ? 'selected' : '' }} {{ $lk ? 'disabled' : '' }}>
                                        {{ $nm }}{{ $lk ? ' (Terkunci)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @if($lockMap['Blok 1'] || $lockMap['Blok 2'])
                                <small class="text-danger d-block mt-1">
                                    <i class="bi bi-lock-fill"></i>
                                    {{ collect($lockMap)->filter()->keys()->implode(' & ') }} terkunci.
                                </small>
                            @endif
                        </div>
                    </div>

                    @include('partials.input-jam', ['sesiMulai' => old('sesi_mulai'), 'sesiSelesai' => old('sesi_selesai')])

                    <div id="peringatanForm" class="mb-3" aria-live="polite"></div>

                    <button type="submit" class="btn btn-scp-accent w-100" {{ ($lockMap['Blok 1'] && $lockMap['Blok 2']) ? 'disabled' : '' }}>
                        Simpan &amp; Uji Jadwal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Ketersediaan ruangan (langsung) + daftar jadwal milik sendiri -->
    <div class="col-lg-7">
        {{-- Ketersediaan ruangan: berubah langsung mengikuti Hari + Ruangan (+ Blok) pada formulir input --}}
        <div class="scp-card mb-3" id="panelKetersediaan">
            <div class="scp-card-header accent-primary">Ketersediaan Ruangan</div>
            <div class="scp-card-body">
                <div id="ketersediaanIsi" aria-live="polite"></div>
            </div>
        </div>

        <div class="scp-card">
            <div class="scp-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Jadwal saya &middot; <span id="judulBlok">{{ $blokTampil }}</span> &middot; <span id="jumlahHasil">0</span> jadwal</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Pilih blok">
                    @foreach(['Blok 1', 'Blok 2'] as $b)
                        <button type="button" class="btn btn-outline-secondary" data-tab-blok="{{ $b }}">{{ $b }}</button>
                    @endforeach
                </div>
            </div>
            <div class="scp-card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabelHasil" data-paginasi="15">
                        <thead class="table-light">
                            <tr>
                                <th>Hari / Jam</th>
                                <th>Kelas &amp; Mapel</th>
                                <th>Ruangan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jadwals as $j)
                                <tr data-blok="{{ $j->cycle_type }}">
                                    <td>
                                        <strong>{{ $j->hari }}</strong><br>
                                        <small class="text-muted">
                                            @php $lbl = \App\Support\SesiPelajaran::label($j->hari, $j->jam_mulai, $j->jam_selesai); @endphp
                                            {{ $lbl ? $lbl . ' · ' : '' }}{{ substr($j->jam_mulai, 0, 5) }} - {{ substr($j->jam_selesai, 0, 5) }}
                                        </small>
                                    </td>
                                    <td>
                                        {{ $j->kelas->id_kelas ?? $j->id_kelas }}<br>
                                        <small class="text-muted">{{ $j->id_mapel }} &mdash; {{ $j->mataPelajaran->nama_mapel ?? $j->id_mapel }}</small>
                                    </td>
                                    <td>{{ $j->ruangan->nama_ruangan ?? $j->id_ruangan_default }}</td>
                                    <td class="text-center text-nowrap">
                                        {{-- Saat blok dikunci Waka, tombol aksi disembunyikan (endpoint juga dijaga di server) --}}
                                        @if($config && $config->isCycleLocked($j->cycle_type))
                                            <span class="text-danger small" title="{{ $j->cycle_type }} dikunci oleh Waka"><i class="bi bi-lock-fill"></i> Terkunci</span>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-jadwal" title="Ubah"
                                                data-action="{{ route('jadwal.update', $j->id_jadwal) }}"
                                                data-kelas="{{ $j->id_kelas }}"
                                                data-mapel="{{ $j->id_mapel }}"
                                                data-ruangan="{{ $j->id_ruangan_default }}"
                                                data-hari="{{ $j->hari }}"
                                                data-blok="{{ $j->cycle_type }}"
                                                data-mulai="{{ substr($j->jam_mulai, 0, 5) }}"
                                                data-selesai="{{ substr($j->jam_selesai, 0, 5) }}">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="{{ route('jadwal.destroy', $j->id_jadwal) }}" method="POST" class="d-inline" data-confirm="Hapus jadwal {{ $j->kelas->id_kelas ?? $j->id_kelas }} &mdash; {{ $j->mataPelajaran->nama_mapel ?? $j->id_mapel }} ({{ $j->hari }}, {{ $j->cycle_type }})?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr id="barisKosong" class="d-none">
                                <td colspan="4" class="text-center text-muted py-4">Anda belum punya jadwal di blok ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal ubah jadwal (hanya untuk guru pemilik jadwal & blok belum dikunci) --}}
@if($guruLogin)
<div class="modal fade" id="modalEditJadwal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formEditJadwal" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Ubah Jadwal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kelas</label>
                    <select name="id_kelas" class="form-select" required>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Mata Pelajaran</label>
                    <select name="id_mapel" class="form-select" required>
                        @foreach($mapels as $m)
                            <option value="{{ $m->id_mapel }}">{{ $m->id_mapel }} &mdash; {{ $m->nama_mapel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Ruangan</label>
                    <select name="id_ruangan_default" class="form-select" required>
                        @foreach($ruangans as $r)
                            <option value="{{ $r->id_ruangan }}">{{ $r->nama_ruangan ?? 'Ruang '.$r->id_ruangan }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hari</label>
                        <select name="hari" class="form-select" required>
                            @foreach(['Senin','Selasa','Rabu','Kamis','Jumat'] as $h)
                                <option value="{{ $h }}">{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Blok</label>
                        <select name="cycle_type" class="form-select" required>
                            @foreach(['Blok 1', 'Blok 2'] as $b)
                                <option value="{{ $b }}" {{ $config && $config->isCycleLocked($b) ? 'disabled' : '' }}>{{ $b }}{{ $config && $config->isCycleLocked($b) ? ' (Terkunci)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @include('partials.input-jam')
                <small class="text-muted d-block mt-3">Perubahan tetap dicek bentrok waktu, ruangan, dan kelas.</small>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-scp-primary btn-sm">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('styles')
<style>
    /* Jadwal milik guru yang sedang login: biru muda + garis kiri (senada warna utama) */
    .table > tbody > tr.jadwal-saya > * { background-color: #EAF1F8; --bs-table-bg-state: #EAF1F8; }
    .table > tbody > tr.jadwal-saya > td:first-child { box-shadow: inset 4px 0 0 var(--scp-primary); }
    .table-hover > tbody > tr.jadwal-saya:hover > * { background-color: #DCE8F4; }
    .badge-jadwal-saya { background: var(--scp-primary); color: #fff; font-weight: 600; }

    /* Ketersediaan ruangan per jam ke- */
    .slot-ruang { display: flex; align-items: center; gap: .75rem; padding: .55rem .75rem; border-radius: 10px; border: 1px solid #e5e7eb; }
    .slot-ruang .slot-no { font-weight: 700; min-width: 5.2rem; line-height: 1.25; }
    .slot-ruang.kosong { background: #f0faf5; border-color: #cdebdc; }
    .slot-ruang.pakai { background: #fef2f2; border-color: #fecaca; }
    .slot-ruang.dipilih { outline: 2px solid var(--scp-primary); outline-offset: 1px; }
</style>
@endpush

@push('scripts')
<script>
// Input jam berbasis sesi: pilihan "jam ke-" mengikuti Hari pada form yang sama.
(function () {
    const SESI = @json(\App\Support\SesiPelajaran::untukJs());

    function bangunOpsi(sel, daftar, nilai, teks) {
        sel.innerHTML = '';
        daftar.forEach(function (s) {
            const o = new Option(teks(s), s.no);
            sel.add(o);
        });
        sel.value = daftar.some(s => String(s.no) === String(nilai)) ? nilai : '';
    }

    function perbarui(grup) {
        const form = grup.closest('form');
        const hari = form.querySelector('[name="hari"]').value;
        const sm = grup.querySelector('.sel-mulai'), ss = grup.querySelector('.sel-selesai');
        const info = grup.querySelector('.durasi-info');
        const semua = SESI[hari] || [];

        if (!semua.length) {
            sm.innerHTML = ss.innerHTML = '<option value="">Pilih hari dulu</option>';
            info.textContent = '';
            return;
        }

        const nilaiMulai = sm.value || grup.dataset.sesiMulai || '';
        const nilaiSelesai = ss.value || grup.dataset.sesiSelesai || '';
        bangunOpsi(sm, semua, nilaiMulai, s => 'Jam ke-' + s.no + ' (' + s.mulai.replace(':', '.') + ')');
        if (!sm.value) { sm.insertAdjacentHTML('afterbegin', '<option value="">Pilih</option>'); sm.value = ''; }

        // Sesi selesai: mulai dari sesi mulai, dan tidak melewati istirahat.
        const awal = semua.find(s => String(s.no) === sm.value);
        const kandidat = awal ? semua.filter(s => s.no >= awal.no && s.kelompok === awal.kelompok) : semua;
        bangunOpsi(ss, kandidat, nilaiSelesai, s => 'Jam ke-' + s.no + ' (' + s.selesai.replace(':', '.') + ')');
        if (!ss.value) { ss.insertAdjacentHTML('afterbegin', '<option value="">Pilih</option>'); ss.value = ''; }

        const akhir = semua.find(s => String(s.no) === ss.value);
        info.textContent = (awal && akhir)
            ? awal.mulai.replace(':', '.') + ' – ' + akhir.selesai.replace(':', '.') + ' (jam ke-' + awal.no + (akhir.no !== awal.no ? '–' + akhir.no : '') + ')'
            : '';
        delete grup.dataset.sesiMulai; delete grup.dataset.sesiSelesai;
    }

    // Dipakai modal ubah: cocokkan jam tersimpan dengan sesi pada hari itu.
    window.aturJam = function (grup, hari, mulai, selesai) {
        const semua = SESI[hari] || [];
        const a = semua.find(s => s.mulai === mulai), b = semua.find(s => s.selesai === selesai);
        grup.querySelector('.sel-mulai').value = '';
        grup.querySelector('.sel-selesai').value = '';
        grup.dataset.sesiMulai = a ? a.no : '';
        grup.dataset.sesiSelesai = b ? b.no : '';
        perbarui(grup);
    };

    document.addEventListener('change', function (e) {
        const form = e.target.closest('form');
        if (!form) return;
        const grup = form.querySelector('.jam-group');
        if (!grup) return;
        if (e.target.name === 'hari' || e.target.classList.contains('sel-mulai') || e.target.classList.contains('sel-selesai')) {
            perbarui(grup);
        }
    });
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.jam-group').forEach(perbarui));
})();

// Daftar "Jadwal Saya" hanya menampilkan satu blok dan mengikuti Blok pada form input (dua arah).
(function () {
    const selBlok = document.getElementById('inputBlok');
    const tombol = document.querySelectorAll('[data-tab-blok]');
    const baris = document.querySelectorAll('#tabelHasil tbody tr[data-blok]');
    const kosong = document.getElementById('barisKosong');
    const jumlah = document.getElementById('jumlahHasil');
    const judul = document.getElementById('judulBlok');
    if (!tombol.length) return;
    let blokSekarang = @json($blokTampil);

    function tampil(blok) {
        let n = 0;
        baris.forEach(function (tr) {
            const cocok = tr.dataset.blok === blok;
            tr.classList.toggle('d-none', !cocok);
            if (cocok) n++;
        });
        kosong.classList.toggle('d-none', n > 0);
        jumlah.textContent = n;
        judul.textContent = blok;
        blokSekarang = blok;
        tombol.forEach(function (t) { t.classList.toggle('active', t.dataset.tabBlok === blok); });
        window.dispatchEvent(new CustomEvent('scp-blok-berubah'));
    }

    // Ganti Blok di form input -> daftar ikut berganti
    if (selBlok) selBlok.addEventListener('change', function () { tampil(selBlok.value); });

    // Klik tab Blok -> form ikut berganti bila blok itu belum dikunci
    tombol.forEach(function (t) {
        t.addEventListener('click', function () {
            const blok = t.dataset.tabBlok;
            if (selBlok) {
                const opsi = Array.from(selBlok.options).find(function (o) { return o.value === blok; });
                if (opsi && !opsi.disabled) selBlok.value = blok;
            }
            tampil(blok);
        });
    });

    tampil(blokSekarang);
})();

// Ketersediaan ruangan & peringatan bentrok langsung (tanpa muat ulang halaman).
// Hanya informasi; penolakan bentrok yang mengikat tetap dilakukan server saat Simpan.
(function () {
    const form = document.getElementById('formInputJadwal');
    const panel = document.getElementById('ketersediaanIsi');
    const peringatan = document.getElementById('peringatanForm');
    if (!form || !panel) return;

    const URL_API = @json(route('jadwal.ketersediaan'));
    let tunda = null, urutan = 0, pengendali = null;

    const esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    const nilai = function (n) { return form.elements[n] ? form.elements[n].value : ''; };
    const titik = function (jam) { return String(jam).replace(':', '.'); };
    const orang = function (o) {
        return '<strong>' + esc(o.kelas) + '</strong> &middot; ' + esc(o.mapel) + ' &middot; ' + esc(o.guru) + (o.milik_saya ? ' <span class="badge badge-jadwal-saya">Anda</span>' : '');
    };

    function gambarSlot(d) {
        if (!d.slots) {
            panel.innerHTML = '<div class="text-muted small text-center py-3"><i class="bi bi-door-open fs-4 d-block mb-1"></i>' +
                'Pilih <strong>hari</strong> dan <strong>ruangan</strong> di formulir untuk melihat jam yang kosong atau terpakai.</div>';
            return;
        }
        const a = parseInt(nilai('sesi_mulai'), 10) || 0;
        const b = parseInt(nilai('sesi_selesai'), 10) || a;
        const sel = form.elements['id_ruangan_default'];
        const namaRuang = sel && sel.selectedOptions[0] ? sel.selectedOptions[0].text.trim() : '';
        const kosong = d.slots.filter(function (s) { return !s.terpakai; }).length;

        let html = '<div class="d-flex flex-wrap justify-content-between gap-1 small text-muted mb-2">' +
            '<span>' + esc(namaRuang) + ' &middot; ' + esc(d.hari) + ' &middot; ' + esc(d.blok) + '</span>' +
            '<span><strong class="text-success">' + kosong + '</strong> dari ' + d.slots.length + ' jam kosong</span></div>' +
            '<div class="d-grid gap-2">';
        d.slots.forEach(function (s) {
            const dipilih = a > 0 && s.no >= a && s.no <= b;
            html += '<div class="slot-ruang ' + (s.terpakai ? 'pakai' : 'kosong') + (dipilih ? ' dipilih' : '') + '">' +
                '<div class="slot-no">Jam ke-' + s.no + '<div class="small fw-normal text-muted">' + titik(s.mulai) + ' &ndash; ' + titik(s.selesai) + '</div></div>' +
                '<div class="flex-grow-1 small">' + (s.terpakai ? s.oleh.map(orang).join('<br>') : '<span class="text-success">Kosong</span>') + '</div>' +
                '<span class="badge ' + (s.terpakai ? 'text-bg-danger' : 'text-bg-success') + '">' + (s.terpakai ? 'Terpakai' : 'Kosong') + '</span></div>';
        });
        panel.innerHTML = html + '</div>';
    }

    function gambarBentrok(d) {
        if (d.pesan_jam) {
            peringatan.innerHTML = '<div class="alert alert-warning py-2 small mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + esc(d.pesan_jam) + '</div>';
        } else if (d.bentrok && d.bentrok.length) {
            peringatan.innerHTML = '<div class="alert alert-danger py-2 small mb-0"><strong><i class="bi bi-x-octagon-fill me-1"></i>Bentrok dengan jadwal lain:</strong><ul class="mb-0 ps-3 mt-1">' +
                d.bentrok.map(function (j) {
                    return '<li>' + orang(j) + '<br><span class="text-muted">' + esc(j.hari) + ', ' + esc(j.jam) + ' &middot; ' + esc(j.ruangan) + '</span> ' +
                        j.alasan.map(function (x) { return '<span class="badge text-bg-danger">' + esc(x) + '</span>'; }).join(' ') + '</li>';
                }).join('') + '</ul></div>';
        } else if (d.bentrok_dicek) {
            peringatan.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Tidak ada bentrok pada pilihan ini.</div>';
        } else {
            peringatan.innerHTML = '';
        }
    }

    function ambil() {
        const p = new URLSearchParams({
            blok: nilai('cycle_type'), hari: nilai('hari'),
            ruangan: nilai('id_ruangan_default'), kelas: nilai('id_kelas'),
            sesi_mulai: nilai('sesi_mulai'), sesi_selesai: nilai('sesi_selesai')
        });
        const saya = ++urutan;
        if (pengendali) pengendali.abort();
        pengendali = new AbortController();

        fetch(URL_API + '?' + p.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: pengendali.signal
        }).then(function (r) {
            if (!r.ok) throw new Error('http ' + r.status);
            return r.json();
        }).then(function (d) {
            if (saya !== urutan) return;      // abaikan balasan lama
            gambarSlot(d);
            gambarBentrok(d);
        }).catch(function (e) {
            if (e.name === 'AbortError') return;
            panel.innerHTML = '<div class="text-muted small text-center py-3">Ketersediaan belum bisa dimuat. Coba pilih ulang ruangan.</div>';
            peringatan.innerHTML = '';
        });
    }

    function jadwalkan() { clearTimeout(tunda); tunda = setTimeout(ambil, 250); }

    form.addEventListener('change', jadwalkan);
    window.addEventListener('scp-blok-berubah', jadwalkan);
    setTimeout(ambil, 300);
})();

// Isi modal "Ubah Jadwal" dari atribut data-* pada tombol yang diklik.
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-edit-jadwal');
    if (!btn) return;
    const form = document.getElementById('formEditJadwal');
    form.action = btn.dataset.action;
    const set = (name, val) => { const el = form.elements[name]; if (el) el.value = val; };
    set('id_kelas', btn.dataset.kelas);
    set('id_mapel', btn.dataset.mapel);
    set('id_ruangan_default', btn.dataset.ruangan);
    set('hari', btn.dataset.hari);
    set('cycle_type', btn.dataset.blok);
    window.aturJam(form.querySelector('.jam-group'), btn.dataset.hari, btn.dataset.mulai, btn.dataset.selesai);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditJadwal')).show();
});
</script>
@endpush
@endsection
