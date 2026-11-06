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

                    <button type="submit" class="btn btn-scp-accent w-100" {{ ($lockMap['Blok 1'] && $lockMap['Blok 2']) ? 'disabled' : '' }}>
                        Simpan &amp; Uji Jadwal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Cek Ketersediaan Jadwal (semua guru, bisa difilter) -->
    <div class="col-lg-7">
        <div class="scp-card mb-3">
            <div class="scp-card-header accent-primary">
                Cek Ketersediaan Jadwal
            </div>
            <div class="scp-card-body">
                <form method="GET" action="{{ route('jadwal.index') }}" class="row g-2" data-auto-filter>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold">Hari</label>
                        <select name="cek_hari" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            @foreach(['Senin','Selasa','Rabu','Kamis','Jumat'] as $h)
                                <option value="{{ $h }}" {{ ($filterAktif['cek_hari'] ?? '') == $h ? 'selected' : '' }}>{{ $h }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="cek_cycle" id="cekCycle" value="{{ $blokTampil }}">
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Kelas</label>
                        <select name="cek_kelas" class="form-select form-select-sm select-search">
                            <option value="">Semua</option>
                            @foreach($kelas as $k)
                                <option value="{{ $k->id_kelas }}" {{ ($filterAktif['cek_kelas'] ?? '') == $k->id_kelas ? 'selected' : '' }}>{{ $k->id_kelas }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-semibold">Ruangan</label>
                        <select name="cek_ruangan" class="form-select form-select-sm select-search">
                            <option value="">Semua</option>
                            @foreach($ruangans as $r)
                                <option value="{{ $r->id_ruangan }}" {{ ($filterAktif['cek_ruangan'] ?? '') == $r->id_ruangan ? 'selected' : '' }}>{{ $r->nama_ruangan }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(array_filter($filterAktif))
                        <div class="col-12 d-flex gap-2 mt-2">
                            <a href="{{ route('jadwal.index') }}" class="btn btn-outline-secondary btn-sm">Atur Ulang</a>
                        </div>
                    @endif
                    <noscript>
                        <div class="col-12 mt-2"><button type="submit" class="btn btn-scp-primary btn-sm">Terapkan</button></div>
                    </noscript>
                </form>
            </div>
        </div>

        <div class="scp-card">
            <div class="scp-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><span id="judulBlok">{{ $blokTampil }}</span> &middot; <span id="jumlahHasil">0</span> jadwal</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Pilih blok">
                    @foreach(['Blok 1', 'Blok 2'] as $b)
                        <button type="button" class="btn btn-outline-secondary" data-tab-blok="{{ $b }}">{{ $b }}</button>
                    @endforeach
                </div>
            </div>
            <div class="scp-card-body p-0 p-md-3">
                @php $bisaMassal = (int) session('id_role') === 4; @endphp
                @if($bisaMassal)
                <div class="massal" data-form-hapus="#formHapusJadwal" data-nama="jadwal" data-peringatan="Jadwal yang sudah punya jurnal dilewati.">
                    <div class="d-flex align-items-center gap-2 mb-2 px-3 px-md-0 pt-2 pt-md-0">
                        <span class="massal-jumlah small text-muted ms-auto"></span>
                        <button type="button" class="btn btn-sm btn-outline-danger massal-tombol" data-aksi="hapus" disabled><i class="bi bi-trash me-1"></i>Hapus</button>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tabelHasil" data-paginasi="15">
                        <thead class="table-light">
                            <tr>
                                @if($bisaMassal)<th style="width:36px"><input type="checkbox" class="form-check-input massal-semua" aria-label="Pilih semua"></th>@endif
                                <th>Hari / Jam</th>
                                <th>Kelas &amp; Mapel</th>
                                <th>Guru</th>
                                <th>Ruangan</th>
                                @if($guruLogin)<th class="text-center">Aksi</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($jadwals as $j)
                                <tr data-blok="{{ $j->cycle_type }}" class="{{ $bisaMassal ? 'massal-baris ' : '' }}{{ $guruLogin && $j->id_guru == $guruLogin->id_guru ? 'jadwal-saya' : '' }}">
                                    @if($bisaMassal)<td><input type="checkbox" class="form-check-input massal-cek" value="{{ $j->id_jadwal }}"></td>@endif
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
                                    <td>
                                        {{ $j->guru->nama_guru ?? $j->id_guru }}
                                        @if($guruLogin && $j->id_guru == $guruLogin->id_guru)
                                            <span class="badge badge-jadwal-saya">Jadwal saya</span>
                                        @endif
                                    </td>
                                    <td>{{ $j->ruangan->nama_ruangan ?? $j->id_ruangan_default }}</td>
                                    @if($guruLogin)
                                    <td class="text-center text-nowrap">
                                        @if($j->id_guru == $guruLogin->id_guru)
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
                                        @endif
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr id="barisKosong" class="d-none">
                                <td colspan="{{ ($guruLogin ? 5 : 4) + ($bisaMassal ? 1 : 0) }}" class="text-center text-muted py-4">Slot kosong.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if($bisaMassal)
                </div>
                <form id="formHapusJadwal" method="POST" action="{{ route('master.jadwal.massal-hapus') }}" class="d-none">@csrf<div class="massal-ids"></div></form>
                @endif
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

// Hasil jadwal hanya menampilkan satu blok dan mengikuti Blok pada form input (dua arah).
(function () {
    const selBlok = document.getElementById('inputBlok');
    const hidden = document.getElementById('cekCycle');
    const tombol = document.querySelectorAll('[data-tab-blok]');
    const baris = document.querySelectorAll('#tabelHasil tbody tr[data-blok]');
    const kosong = document.getElementById('barisKosong');
    const jumlah = document.getElementById('jumlahHasil');
    const judul = document.getElementById('judulBlok');
    if (!hidden || !tombol.length) return;

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
        hidden.value = blok;
        if (window.massalSegarkan) window.massalSegarkan();
        tombol.forEach(function (t) { t.classList.toggle('active', t.dataset.tabBlok === blok); });
    }

    // Ganti Blok di form input -> Hasil ikut berganti
    if (selBlok) selBlok.addEventListener('change', function () { tampil(selBlok.value); });

    // Klik tab Blok di Hasil -> form ikut berganti bila blok itu belum dikunci
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

    tampil(hidden.value);
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
