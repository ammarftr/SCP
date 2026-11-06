@extends('layouts.app')

@section('title', 'Laporan Izin Harian')
@section('subtitle', 'Izin & sakit yang berlaku pada ' . \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y'))

@section('content')
@php
    $statusLabel = ['Approved' => 'Disetujui', 'Pending' => 'Menunggu', 'Rejected' => 'Ditolak'];
    $statusWarna = ['Approved' => 'success', 'Pending' => 'warning text-dark', 'Rejected' => 'danger'];
@endphp

@if($kelasWali !== null)
    @if($kelasWali === '')
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Akun Anda belum ditetapkan sebagai wali dari kelas mana pun. Hubungi Waka Kurikulum.</div>
    @else
        <p class="text-muted small mb-3">Kelas perwalian: <strong>{{ $kelasWali }}</strong></p>
    @endif
@endif

<div class="scp-card mb-3">
    <div class="scp-card-body">
        <form method="GET" action="{{ route('kesiswaan.laporan.izin') }}" class="row g-2 align-items-center" data-auto-filter>
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
            <div class="col-auto">
                <select name="jenis" class="form-select">
                    <option value="">Semua Jenis</option>
                    <option value="Sakit" {{ $jenis === 'Sakit' ? 'selected' : '' }}>Sakit</option>
                    <option value="Izin" {{ $jenis === 'Izin' ? 'selected' : '' }}>Izin</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach($statusLabel as $kode => $nama)
                        <option value="{{ $kode }}" {{ $status === $kode ? 'selected' : '' }}>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-5 col-md-3">
                <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="Cari nama / NIS / alasan...">
            </div>
            <noscript>
                <div class="col-auto"><button type="submit" class="btn btn-scp-primary">Tampilkan</button></div>
            </noscript>
        </form>
    </div>
</div>

{{-- Ringkasan: hanya izin yang berlaku pada tanggal ini, tidak kena filter jenis/status/pencarian --}}
<div class="row g-3 mb-3">
    @foreach([
        ['Sakit (disetujui)', $ringkasan['Sakit'], 'bi-heart-pulse', false],
        ['Izin (disetujui)', $ringkasan['Izin'], 'bi-file-earmark-text', false],
        ['Menunggu verifikasi', $ringkasan['Pending'], 'bi-hourglass-split', false],
        ['Ditolak', $ringkasan['Rejected'], 'bi-x-circle', true],
    ] as [$label, $angka, $ikon, $merah])
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:44px;height:44px;border-radius:10px;{{ $merah ? 'background:#FDEDED;color:#C0392B;' : 'background:var(--scp-accent-soft);color:var(--scp-primary);' }}">
                    <i class="bi {{ $ikon }} fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $angka }}</div>
                    <div class="text-muted small">{{ $label }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h6 class="fw-bold mb-0">Daftar Izin Berlaku ({{ $total }})</h6>
    <a href="{{ route('kesiswaan.rekap.absensi', ['tanggal' => $tanggal, 'kelas' => $kelasWali === null ? $kelasFilter : null]) }}" class="btn btn-sm btn-outline-dark">
        <i class="bi bi-bar-chart"></i> Rekap Absensi
    </a>
</div>

@forelse($laporanPerKelas as $namaKelas => $daftar)
    <div class="scp-card mb-3">
        <div class="scp-card-header accent-primary">
            Kelas {{ $namaKelas }} <span class="badge bg-secondary ms-1">{{ $jumlahPerKelas[$namaKelas] ?? $daftar->count() }} pengajuan</span>
        </div>
        <div class="scp-card-body p-0 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama &amp; NIS</th>
                            <th>Jenis</th>
                            <th>Periode</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Presensi</th>
                            <th >Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($daftar as $izin)
                        @php
                            $mulai = \Carbon\Carbon::parse($izin->start_date);
                            $selesai = \Carbon\Carbon::parse($izin->end_date);
                            $hariKe = (int) abs($mulai->copy()->startOfDay()->diffInDays(\Carbon\Carbon::parse($tanggal)->startOfDay())) + 1;
                            $lamaHari = (int) abs($mulai->copy()->startOfDay()->diffInDays($selesai->copy()->startOfDay())) + 1;
                            $jumlahKoreksi = (int) ($koreksi[$izin->id] ?? 0);
                            $jumlahPresensi = (int) ($presensiSiswa[$izin->student_id] ?? 0);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $izin->student->nama_siswa ?? 'Siswa Tidak Ditemukan' }}</strong><br>
                                <small class="text-muted">NIS: {{ $izin->student->nisn ?? '-' }}</small>
                            </td>
                            <td><span class="badge bg-{{ $izin->jenis == 'Sakit' ? 'info' : 'secondary' }}">{{ $izin->jenis }}</span></td>
                            <td class="text-nowrap">
                                {{ $mulai->format('d/m/Y') }}
                                @if($lamaHari > 1)
                                    – {{ $selesai->format('d/m/Y') }}
                                    <small class="d-block text-muted">Hari ke-{{ $hariKe }} dari {{ $lamaHari }}</small>
                                @endif
                            </td>
                            <td>{{ $izin->reason }}</td>
                            <td>
                                <span class="badge bg-{{ $statusWarna[$izin->status_approval] ?? 'secondary' }}">
                                    {{ $statusLabel[$izin->status_approval] ?? $izin->status_approval }}
                                </span>
                                @if($izin->status_approval === 'Rejected' && $izin->alasan_penolakan)
                                    <small class="d-block text-muted mt-1">{{ $izin->alasan_penolakan }}</small>
                                @endif
                                @if($izin->verified_by && isset($namaVerifikator[$izin->verified_by]))
                                    <small class="d-block text-muted mt-1">oleh {{ $namaVerifikator[$izin->verified_by] }}</small>
                                @endif
                            </td>
                            <td>
                                @if($izin->status_approval !== 'Approved')
                                    <span class="text-muted small">-</span>
                                @elseif($jumlahKoreksi > 0)
                                    <span class="badge bg-success">{{ $jumlahKoreksi }} mapel dikoreksi jadi {{ $izin->jenis }}</span>
                                @elseif($jumlahPresensi > 0)
                                    <span class="text-muted small">Presensi guru sudah tercatat</span>
                                @else
                                    <span class="text-muted small">Belum ada jurnal guru</span>
                                @endif
                            </td>
                            <td >
                                @if($izin->document_path)
                                    <a href="{{ route('berkas.izin', $izin->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">Lihat</a>
                                @else
                                    -
                                @endif
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
            Tidak ada izin/sakit yang berlaku pada tanggal ini{{ ($jenis || $status || $keyword !== '') ? ' dengan filter yang dipilih' : '' }}.
        </div>
    </div>
@endforelse

@if($halamanIzin->hasPages())
    <div class="scp-card">
        @include('partials.paginasi', ['paginator' => $halamanIzin, 'label' => 'pengajuan'])
    </div>
@endif

<p class="text-muted small mt-3">
    Laporan ini berisi <strong>pengajuan izin</strong> yang periodenya mencakup tanggal terpilih. Untuk kehadiran per mata pelajaran
    (termasuk siswa Alfa tanpa izin), lihat <a href="{{ route('kesiswaan.rekap.absensi', ['tanggal' => $tanggal]) }}">Rekap Absensi</a>.
</p>
@endsection
