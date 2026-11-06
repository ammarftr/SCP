@extends('layouts.app')

@section('title', 'Rekap Absensi')
@section('subtitle', 'Kehadiran siswa, ' . \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y'))

@section('content')
@if($kelasWali !== null)
    @if($kelasWali === '')
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Akun Anda belum ditetapkan sebagai wali dari kelas mana pun. Hubungi Waka Kurikulum.</div>
    @else
        <p class="text-muted small mb-3">Kelas perwalian: <strong>{{ $kelasWali }}</strong></p>
    @endif
@endif

<div class="scp-card mb-3">
    <div class="scp-card-body">
        <form method="GET" action="{{ route('kesiswaan.rekap.absensi') }}" class="row g-2 align-items-center" data-auto-filter>
            <div class="col-auto">
                <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-control">
            </div>
            @if($kelasWali === null)
            <div class="col-auto" style="min-width:190px;">
                <select name="kelas" class="form-select select-search">
                    <option value="">Semua Kelas</option>
                    @foreach($daftarKelas as $k)
                        <option value="{{ $k }}" {{ $kelasFilter == $k ? 'selected' : '' }}>{{ $k }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-sm-5 col-md-3">
                <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="Cari nama / NIS siswa...">
            </div>
            @if($tampilkanSemua)
                <input type="hidden" name="semua" value="1">
            @endif
            <noscript>
                <div class="col-auto"><button type="submit" class="btn btn-scp-primary">Tampilkan</button></div>
            </noscript>
        </form>
    </div>
</div>

{{-- Ringkasan angka — biar gak perlu scroll list Hadir yang panjang cuma buat tau jumlahnya --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-check-circle fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $ringkasan['Hadir'] ?? 0 }}</div>
                    <div class="text-muted small">Hadir</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-heart-pulse fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $ringkasan['Sakit'] ?? 0 }}</div>
                    <div class="text-muted small">Sakit</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-file-earmark-text fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $ringkasan['Izin'] ?? 0 }}</div>
                    <div class="text-muted small">Izin</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:#FDEDED;color:#C0392B;">
                    <i class="bi bi-x-circle fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $ringkasan['Alfa'] ?? 0 }}</div>
                    <div class="text-muted small">Alfa</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h6 class="fw-bold mb-0">
        {{ $tampilkanSemua ? 'Semua Data Kehadiran' : 'Siswa Yang Perlu Perhatian (Sakit/Izin/Alfa)' }}
        <a href="{{ route('kesiswaan.laporan.izin', ['tanggal' => $tanggal, 'kelas' => $kelasWali === null ? $kelasFilter : null]) }}" class="btn btn-sm btn-link text-decoration-none ms-1">
            <i class="bi bi-clipboard-data"></i> Lihat laporan izin tanggal ini
        </a>
    </h6>
    @if($tampilkanSemua)
        <a href="{{ route('kesiswaan.rekap.absensi', ['tanggal' => $tanggal, 'q' => $keyword, 'kelas' => $kelasWali === null ? $kelasFilter : null]) }}" class="btn btn-sm btn-scp-primary">
            Sembunyikan Yang Hadir
        </a>
    @else
        <a href="{{ route('kesiswaan.rekap.absensi', ['tanggal' => $tanggal, 'q' => $keyword, 'kelas' => $kelasWali === null ? $kelasFilter : null, 'semua' => 1]) }}" class="btn btn-sm btn-outline-dark">
            Tampilkan Semua (termasuk Hadir)
        </a>
    @endif
</div>

<p class="text-muted small mb-2"><i class="bi bi-file-earmark-check"></i> = status berasal dari izin yang disetujui (bukan diinput guru).</p>

@forelse($rekapPerKelas as $namaKelas => $siswaList)
    <div class="scp-card mb-3">
        <div class="scp-card-header accent-primary">
            Kelas {{ $namaKelas }} <span class="badge bg-secondary ms-1">{{ $jumlahPerKelas[$namaKelas] ?? $siswaList->count() }} siswa</span>
        </div>
        <div class="scp-card-body p-0 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th style="width:220px;">Nama Siswa</th><th>NIS</th><th>Status per Mata Pelajaran</th></tr>
                    </thead>
                    <tbody>
                    @foreach($siswaList as $s)
                        <tr>
                            <td class="fw-semibold">
                                {{ $s->nama_siswa }}
                                <span class="badge bg-light text-dark border fw-normal ms-1 text-nowrap">{{ $s->kelas }}</span>
                            </td>
                            <td>{{ $s->nisn }}</td>
                            <td>
                                @php $colors = ['Hadir' => 'success', 'Izin' => 'warning', 'Sakit' => 'info', 'Alfa' => 'danger']; @endphp
                                @foreach($s->detail as $d)
                                    <span class="badge bg-{{ $colors[$d['status']] ?? 'secondary' }} me-1 mb-1">
                                        {{ $d['mapel'] }}: {{ $d['status'] }}
                                        @if(!empty($d['dari_izin']))
                                            <i class="bi bi-file-earmark-check ms-1" title="Dari izin yang disetujui" aria-label="Dari izin yang disetujui"></i>
                                        @endif
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@empty
    <div class="scp-card">
        <div class="scp-card-body text-center text-muted py-5">
            {{ $tampilkanSemua ? 'Belum ada data presensi untuk tanggal ini.' : 'Tidak ada siswa Sakit/Izin/Alfa pada tanggal ini.' }}
        </div>
    </div>
@endforelse

@if($halamanSiswa->hasPages())
    <div class="scp-card">
        @include('partials.paginasi', ['paginator' => $halamanSiswa, 'label' => 'siswa'])
    </div>
@endif
@endsection
