@extends('layouts.app')

@section('title', 'Verifikasi Izin Siswa')
@section('subtitle', $tampilkanRiwayat ? 'Menampilkan semua riwayat pengajuan' : 'Pengajuan hari ini (' . now()->translatedFormat('d F Y') . ') dan yang masih menunggu')

@section('content')
@if($kelasWali !== null)
    @if($kelasWali === '')
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Akun Anda belum ditetapkan sebagai wali dari kelas mana pun. Hubungi Waka Kurikulum.</div>
    @else
        <p class="text-muted small mb-3">Kelas perwalian: <strong>{{ $kelasWali }}</strong></p>
    @endif
@endif

@if(!empty($kelasBk) || (isset($kelasBk) && $kelasBk === [] && (int) session('id_role') === 7))
    @if(empty($kelasBk))
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> Akun Anda belum ditetapkan mengampu kelas mana pun. Hubungi Waka Kurikulum.</div>
    @else
        <p class="text-muted small mb-3">Kelas yang diampu: <strong>{{ implode(', ', $kelasBk) }}</strong></p>
    @endif
@endif

<div class="scp-card mb-3">
    <div class="scp-card-body">
        <form method="GET" action="{{ route('kesiswaan.izin.index') }}" class="row g-2 align-items-center" data-auto-filter>
            <div class="col-sm-6 col-md-4">
                <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="Cari nama siswa / NIS / alasan...">
            </div>
            <noscript>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Cari</button></div>
            </noscript>
            @if($tampilkanRiwayat)
                <input type="hidden" name="riwayat" value="1">
            @endif
            <div class="col-auto ms-auto">
                @if($tampilkanRiwayat)
                    <a href="{{ route('kesiswaan.izin.index', ['q' => $keyword]) }}" class="btn btn-scp-primary">
                        Lihat Hari Ini Saja
                    </a>
                @else
                    <a href="{{ route('kesiswaan.izin.index', ['riwayat' => 1, 'q' => $keyword]) }}" class="btn btn-outline-dark">
                        Lihat Semua Riwayat
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="scp-card">
    <div class="scp-card-header" style="color:var(--scp-primary);">
        Daftar Pengajuan Izin Siswa ({{ $pendingLeaves->total() }})
    </div>
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama &amp; NIS Siswa</th>
                        <th>Jenis</th>
                        <th>Tanggal</th>
                        <th>Alasan</th>
                        <th>Dokumen</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingLeaves as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->student->nama_siswa ?? 'Siswa Tidak Ditemukan' }}</strong><br>
                            <small class="text-muted">NIS: {{ $item->student->nisn ?? '-' }}</small>
                        </td>
                        <td><span class="badge bg-{{ $item->jenis=='Sakit'?'info':'secondary' }}">{{ $item->jenis }}</span></td>
                        <td>{{ $item->start_date }} s/d {{ $item->end_date }}</td>
                        <td>{{ $item->reason }}</td>
                        <td>
                            @if($item->document_path)
                                <a href="{{ route('berkas.izin', $item->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">Lihat</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($item->status_approval == 'Approved')
                                <span class="badge bg-success">Disetujui</span>
                            @elseif($item->status_approval == 'Rejected')
                                <span class="badge bg-danger">Ditolak</span>
                                @if($item->alasan_penolakan)
                                    <small class="d-block text-muted mt-1">{{ $item->alasan_penolakan }}</small>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark">Menunggu</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($item->status_approval == 'Pending')
                                <form action="{{ route('kesiswaan.izin.update', $item->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="status_approval" value="Approved">
                                    <button type="submit" class="btn btn-sm btn-success">Setujui</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#tolak{{ $item->id }}">Tolak</button>

                                <div class="modal fade text-start" id="tolak{{ $item->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('kesiswaan.izin.update', $item->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status_approval" value="Rejected">
                                                <div class="modal-header">
                                                    <h6 class="modal-title fw-bold">Tolak Izin: {{ $item->student->nama_siswa ?? '-' }}</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <label class="form-label small fw-semibold">Alasan Penolakan</label>
                                                    <textarea name="alasan_penolakan" class="form-control" rows="3" maxlength="500" required placeholder="Tulis alasan izin ditolak..."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-danger">Tolak Izin</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editStatus{{ $item->id }}">
                                    Ubah Keputusan
                                </button>

                                <div class="modal fade" id="editStatus{{ $item->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="{{ route('kesiswaan.izin.update', $item->id) }}" method="POST">
                                                @csrf
                                                <div class="modal-header">
                                                    <h6 class="modal-title fw-bold">Ubah Keputusan: {{ $item->student->nama_siswa ?? '-' }}</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <label class="form-label small fw-semibold">Status Verifikasi</label>
                                                    <select name="status_approval" class="form-select" required>
                                                        <option value="Pending" {{ $item->status_approval=='Pending'?'selected':'' }}>Menunggu</option>
                                                        <option value="Approved" {{ $item->status_approval=='Approved'?'selected':'' }}>Disetujui</option>
                                                        <option value="Rejected" {{ $item->status_approval=='Rejected'?'selected':'' }}>Ditolak</option>
                                                    </select>
                                                    <div class="mt-3 kotak-alasan {{ $item->status_approval == 'Rejected' ? '' : 'd-none' }}">
                                                        <label class="form-label small fw-semibold">Alasan Penolakan</label>
                                                        <textarea name="alasan_penolakan" class="form-control" rows="3" maxlength="500" {{ $item->status_approval == 'Rejected' ? 'required' : 'disabled' }} placeholder="Tulis alasan izin ditolak...">{{ $item->alasan_penolakan }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-scp-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        {{ $tampilkanRiwayat ? 'Belum ada riwayat pengajuan izin.' : 'Tidak ada pengajuan izin yang perlu diproses.' }}
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginasi', ['paginator' => $pendingLeaves, 'label' => 'pengajuan'])
    </div>
</div>

@push('scripts')
<script>
// Kotak alasan hanya muncul (dan wajib diisi) saat status dipilih "Ditolak".
document.addEventListener('change', function (e) {
    if (e.target.name !== 'status_approval' || e.target.tagName !== 'SELECT') return;
    const kotak = e.target.closest('form').querySelector('.kotak-alasan');
    if (!kotak) return;
    const tolak = e.target.value === 'Rejected';
    const ta = kotak.querySelector('textarea');
    kotak.classList.toggle('d-none', !tolak);
    ta.disabled = !tolak;
    ta.required = tolak;
});
</script>
@endpush
@endsection
