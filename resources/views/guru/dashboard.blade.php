@extends('layouts.app')

@section('title', 'Beranda Guru')
@section('subtitle', now()->translatedFormat('l, d F Y') . ' · ' . $activeCycle)

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-1">Selamat datang, {{ session('nama_lengkap') }}</h4>
</div>

<div class="scp-card mb-4">
    <div class="scp-card-body">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-auto">
                <div class="scp-chip-label">Sekarang</div>
                <span class="scp-chip chip-active">
                    {{ $activeCycle }}
                </span>
            </div>
            <div class="col-12 col-md-auto">
                <div class="scp-chip-label">Status kunci jadwal</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(['Blok 1' => $lockB1, 'Blok 2' => $lockB2] as $nm => $lk)
                        <span class="scp-chip {{ $lk ? 'chip-locked' : 'chip-open' }}">
                            <i class="bi {{ $lk ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i> {{ $nm }}: {{ $lk ? 'Terkunci' : 'Terbuka' }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@if(!empty($kelasWali) || !empty($sebagaiBk))
<div class="scp-card mb-4" @if($izinMenunggu > 0) style="border-color:#fde68a;" @endif>
    <div class="scp-card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <div class="fw-semibold">@if(!empty($sebagaiBk)) Guru BK @else Wali Kelas {{ $kelasWali }} @endif</div>
            <div class="text-muted small">
                @if(!empty($sebagaiBk))
                    @if(empty($kelasBk))
                        <span class="text-danger">Anda belum ditetapkan mengampu kelas mana pun. Hubungi Waka Kurikulum.</span><br>
                    @else
                        Kelas yang diampu: {{ implode(', ', $kelasBk) }}.<br>
                    @endif
                @endif
                @if($izinMenunggu > 0)
                    {{ $izinMenunggu }} pengajuan izin siswa menunggu persetujuan Anda.
                @else
                    Tidak ada pengajuan izin yang menunggu.
                @endif
            </div>
        </div>
        <a href="{{ route('kesiswaan.izin.index', ['riwayat' => 1]) }}" class="btn btn-sm {{ $izinMenunggu > 0 ? 'btn-warning' : 'btn-outline-secondary' }}">
            Verifikasi Izin
        </a>
    </div>
</div>
@endif

@if($alerts->count() > 0)
<div class="scp-card mb-4" style="border-color:#fecaca;">
    <div class="scp-card-header" style="color:#dc2626;">
        <i class="bi bi-exclamation-triangle-fill"></i> Anda Punya {{ $alerts->count() }} Jurnal Yang Belum Diisi
    </div>
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Kelas &amp; Mapel</th><th>Jam Selesai</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach($alerts as $a)
                    <tr>
                        <td>{{ $a->kelas->id_kelas ?? $a->id_kelas }} &mdash; {{ $a->mataPelajaran->nama_mapel ?? $a->id_mapel }}</td>
                        <td><span class="badge bg-danger">Selesai {{ $a->jam_selesai }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('jurnal.index', ['schedule_id' => $a->id_jadwal]) }}" class="btn btn-sm btn-danger">Isi Jurnal</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <div class="scp-card h-100">
            <div class="scp-card-header accent-primary">Jadwal Hari Ini</div>
            <div class="scp-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                        @forelse($jadwalHariIni as $j)
                            <tr>
                                <td>
                                    <span class="badge bg-secondary mb-1">{{ $j->jam_mulai }} - {{ $j->jam_selesai }}</span><br>
                                    <strong>{{ $j->mataPelajaran->nama_mapel ?? $j->id_mapel }}</strong><br>
                                    <small class="text-muted">Kelas {{ $j->kelas->id_kelas ?? $j->id_kelas }} &middot; Ruang {{ $j->ruangan->nama_ruangan ?? $j->id_ruangan_default }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4">Tidak ada jadwal mengajar hari ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="scp-card h-100">
            <div class="scp-card-header">Jadwal Besok</div>
            <div class="scp-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                        @forelse($jadwalBesok as $j)
                            <tr>
                                <td>
                                    <span class="badge bg-secondary mb-1">{{ $j->jam_mulai }} - {{ $j->jam_selesai }}</span><br>
                                    <strong>{{ $j->mataPelajaran->nama_mapel ?? $j->id_mapel }}</strong><br>
                                    <small class="text-muted">Kelas {{ $j->kelas->id_kelas ?? $j->id_kelas }} &middot; Ruang {{ $j->ruangan->nama_ruangan ?? $j->id_ruangan_default }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4">Tidak ada jadwal mengajar besok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
