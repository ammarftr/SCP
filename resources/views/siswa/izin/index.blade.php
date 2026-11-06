@extends('layouts.app')

@section('title', 'Izin & Kehadiran')

@section('content')
<div class="row g-3">
    <!-- Form Pengajuan -->
    <div class="col-lg-4">
        <div class="scp-card">
            <div class="scp-card-header accent-primary">
                Form Surat Izin
            </div>
            <div class="scp-card-body">
                <form action="{{ route('siswa.izin.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Jenis</label>
                        <select name="jenis" class="form-select" required>
                            <option value="Sakit">Sakit</option>
                            <option value="Izin">Izin (keperluan lain)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" min="{{ now()->subDays(\App\Models\LeaveRequest::MAKS_HARI_TERLAMBAT)->toDateString() }}" required>
                        <div class="form-text">Maksimal {{ \App\Models\LeaveRequest::MAKS_HARI_TERLAMBAT }} hari setelah tanggal tidak hadir.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Alasan (cth: Sakit Demam / Izin Acara)</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Bukti (Surat Dokter/Foto)</label>
                        @include('partials.bukti-upload', ['name' => 'document', 'prefix' => 'izin'])
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100">
                        Kirim Pengajuan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabs: Riwayat Izin & Rekap Kehadiran -->
    <div class="col-lg-8">
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-riwayat" type="button">Riwayat Izin</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-kehadiran" type="button">Rekap Kehadiran</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-riwayat">
                <div class="scp-card">
                    <div class="scp-card-body p-0 p-md-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Jenis</th><th>Tanggal</th><th>Alasan</th><th>Dokumen</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($riwayatIzin as $izin)
                                    <tr>
                                        <td><span class="badge bg-{{ $izin->jenis=='Sakit'?'info':'secondary' }}">{{ $izin->jenis }}</span></td>
                                        <td>{{ $izin->start_date }} s/d {{ $izin->end_date }}</td>
                                        <td>{{ $izin->reason }}</td>
                                        <td>
                                            @if($izin->document_path)
                                                <a href="{{ route('berkas.izin', $izin->id) }}" target="_blank" class="btn btn-sm btn-outline-info">Lihat</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @if($izin->status_approval == 'Approved')
                                                <span class="badge bg-success">Disetujui</span>
                                            @elseif($izin->status_approval == 'Rejected')
                                                <span class="badge bg-danger">Ditolak</span>
                                                @if($izin->alasan_penolakan)
                                                    <small class="d-block text-muted mt-1">{{ $izin->alasan_penolakan }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-warning text-dark">Menunggu</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat izin.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @include('partials.paginasi', ['paginator' => $riwayatIzin, 'label' => 'pengajuan'])
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-kehadiran">
                <div class="scp-card">
                    <div class="scp-card-header">
                        Rekap per jam pelajaran, terisi dari jurnal guru
                    </div>
                    <div class="scp-card-body p-0 p-md-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Tanggal</th><th>Mata Pelajaran</th><th class="text-center">Status</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($rekapKehadiran as $r)
                                    @php
                                        $colors = ['Hadir' => 'success', 'Izin' => 'warning', 'Sakit' => 'info', 'Alfa' => 'danger'];
                                    @endphp
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($r->date)->translatedFormat('d M Y') }}</td>
                                        <td>{{ $r->nama_mapel }}</td>
                                        <td class="text-center"><span class="badge bg-{{ $colors[$r->status] ?? 'secondary' }}">{{ $r->status }}</span></td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada data kehadiran.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @include('partials.paginasi', ['paginator' => $rekapKehadiran, 'label' => 'catatan'])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Setelah pindah halaman, buka kembali tab yang tadi dipakai (lewat #fragment di URL)
document.addEventListener('DOMContentLoaded', function () {
    var tombol = location.hash && document.querySelector('[data-bs-target="' + location.hash + '"]');
    if (tombol && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tombol).show();
});
</script>
<script src="{{ asset('js/bukti-upload.js') }}?v={{ @filemtime(public_path('js/bukti-upload.js')) }}"></script>
@endpush
