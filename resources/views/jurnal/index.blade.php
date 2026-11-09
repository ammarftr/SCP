@extends('layouts.app')

@section('title', 'Jurnal Mengajar')

@section('content')
<div class="row g-3">
    <!-- Form Input Jurnal -->
    <div class="col-lg-6">
        <div class="scp-card">
            <div class="scp-card-header" style="color:var(--scp-primary);">
                Form Input Jurnal &amp; Presensi
            </div>
            <div class="scp-card-body">
                @if($jadwalHariIni->isEmpty())
                    <div class="alert alert-secondary small mb-0">
                        Tidak ada jadwal mengajar hari ini.
                    </div>
                @else
                <form action="{{ route('jurnal.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Guru Pengajar</label>
                        <input type="text" class="form-control bg-light fw-bold" value="{{ $guru->nama_guru }}" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Jadwal Mengajar (Hari Ini)</label>
                        <select name="schedule_id" id="scheduleSelect" class="form-select select-search" required onchange="showRoster(this.value)">
                            <option value="">-- Pilih Jam Pelajaran --</option>
                            @foreach($jadwalHariIni as $j)
                                <option value="{{ $j->id_jadwal }}" {{ (string)$preselectId === (string)$j->id_jadwal ? 'selected' : '' }}>
                                    {{ substr($j->jam_mulai, 0, 5) }} - {{ substr($j->jam_selesai, 0, 5) }} | {{ $j->mataPelajaran->nama_mapel ?? $j->id_mapel }} | Kelas {{ $j->kelas->id_kelas ?? $j->id_kelas }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Deskripsi Topik / Materi</label>
                        <textarea name="topic_description" class="form-control" rows="2" required placeholder="Tuliskan materi pembelajaran..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Foto Bukti KBM</label>
                        @include('partials.bukti-upload', ['name' => 'photo', 'prefix' => 'jurnal', 'onlyImage' => true, 'cameraOnly' => true])
                    </div>

                    {{-- Roster presensi manual per jadwal — sudah disarankan otomatis dari izin yang Approved,
                         tapi Guru bisa ubah manual per siswa (misal tandai Alfa kalau siswa kabur/tidak masuk kelas) --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Presensi Siswa</label>
                        <div class="border rounded p-2" id="rosterPlaceholder" style="max-height:320px; overflow-y:auto;">
                            <p class="text-muted small mb-0 text-center py-3">Pilih jadwal dulu.</p>
                        </div>

                        @foreach($jadwalHariIni as $j)
                            <div class="roster-block d-none" data-schedule="{{ $j->id_jadwal }}">
                                @forelse(($rosterPerJadwal[$j->id_jadwal] ?? []) as $s)
                                    <div class="py-1 border-bottom">
                                      <div class="d-flex justify-content-between align-items-center">
                                        <span class="small">{{ $s['nama_siswa'] }}</span>
                                        <select name="attendance[{{ $s['id_siswa'] }}]" class="form-select form-select-sm sel-presensi" style="width:120px;">
                                            <option value="Hadir" {{ $s['saran']=='Hadir'?'selected':'' }}>Hadir</option>
                                            <option value="Sakit" {{ $s['saran']=='Sakit'?'selected':'' }}>Sakit</option>
                                            <option value="Izin" {{ $s['saran']=='Izin'?'selected':'' }}>Izin</option>
                                            <option value="Alfa" {{ $s['saran']=='Alfa'?'selected':'' }}>Alfa</option>
                                        </select>
                                      </div>
                                      <input type="text" name="keterangan[{{ $s['id_siswa'] }}]" value="{{ $s['keterangan'] }}" maxlength="255"
                                             class="form-control form-control-sm mt-1 ket-presensi {{ $s['saran']=='Hadir' ? 'd-none' : '' }}" placeholder="Keterangan, mis. rapat OSIS">
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0">Belum ada siswa di kelas ini.</p>
                                @endforelse
                            </div>
                        @endforeach

                    </div>

                    <button type="submit" class="btn btn-scp-accent w-100">
                        Simpan Jurnal &amp; Presensi
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat Jurnal -->
    <div class="col-lg-6">
        <div class="scp-card">
            <div class="scp-card-header">
                Riwayat Jurnal Mengajar (Terbaru di Atas)
            </div>
            <div class="scp-card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Detail Jadwal</th>
                                <th>Topik</th>
                                <th>Foto</th>
                                <th>Presensi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($jurnalList as $item)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($item->date)->translatedFormat('d M Y') }}</td>
                                    <td>
                                        @if($item->jadwal)
                                            <strong>{{ $item->jadwal->kelas->id_kelas ?? '-' }}</strong> - {{ $item->jadwal->mataPelajaran->nama_mapel ?? '-' }}<br>
                                            <small class="text-muted">{{ $item->jadwal->hari }}, {{ substr($item->jadwal->jam_mulai, 0, 5) }} - {{ substr($item->jadwal->jam_selesai, 0, 5) }}</small>
                                        @else
                                            <span class="text-muted">Jadwal dihapus</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->topic_description }}</td>
                                    <td>
                                        @if($item->photo_path)
                                            <a href="{{ route('berkas.jurnal', $item->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">Lihat</a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @php
                                            $pr = $presensiPerJurnal[$item->id] ?? collect();
                                            $hitung = $pr->countBy('status');
                                        @endphp
                                        @if($pr->isEmpty())
                                            <span class="text-muted">-</span>
                                        @else
                                            <small class="d-block text-muted mb-1">
                                                H {{ $hitung['Hadir'] ?? 0 }} · S {{ $hitung['Sakit'] ?? 0 }} · I {{ $hitung['Izin'] ?? 0 }} · A {{ $hitung['Alfa'] ?? 0 }}
                                            </small>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-ubah-presensi" data-jurnal="{{ $item->id }}">Ubah</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada jurnal.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @include('partials.paginasi', ['paginator' => $jurnalList, 'label' => 'jurnal'])
            </div>
        </div>
    </div>
</div>

{{-- Modal ubah status kehadiran --}}
<div class="modal fade" id="modalPresensi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="formPresensi" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Ubah Status Kehadiran</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="daftarPresensi"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-scp-primary btn-sm">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/bukti-upload.js') }}?v={{ @filemtime(public_path('js/bukti-upload.js')) }}"></script>
<script>
function showRoster(scheduleId) {
    const placeholder = document.getElementById('rosterPlaceholder');

    document.querySelectorAll('.roster-block').forEach(function (block) {
        block.classList.add('d-none');
        // Nonaktifkan semua input di block yang disembunyikan biar TIDAK ikut ke-submit.
        // Kalau tidak di-disable, input tersembunyi tetap terkirim dan bisa menimpa
        // pilihan di block yang sedang aktif (karena nama field-nya sama: attendance[id_siswa]).
        block.querySelectorAll('select, input').forEach(function (el) { el.disabled = true; });
    });

    if (!scheduleId) {
        placeholder.classList.remove('d-none');
        return;
    }

    const target = document.querySelector('.roster-block[data-schedule="' + scheduleId + '"]');
    if (target) {
        placeholder.classList.add('d-none');
        target.classList.remove('d-none');
        target.querySelectorAll('select, input').forEach(function (el) { el.disabled = false; });
    } else {
        placeholder.classList.remove('d-none');
    }
}

// Modal ubah status kehadiran: daftar siswa diambil dari data jurnal yang diklik.
(function () {
    const DATA = {{ \Illuminate\Support\Js::from($presensiPerJurnal->map(fn($p) => $p->map(fn($r) => ['id' => $r->student_id, 'nama' => $r->nama_siswa, 'status' => $r->status, 'ket' => $r->keterangan])->values())) }};
    const URL_UBAH = {{ \Illuminate\Support\Js::from(route('jurnal.presensi.ubah', ['id' => '__ID__'])) }};
    const STATUS = ['Hadir', 'Sakit', 'Izin', 'Alfa'];
    const esc = (t) => String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-ubah-presensi');
        if (!btn) return;
        const id = btn.dataset.jurnal;
        document.getElementById('formPresensi').action = URL_UBAH.replace('__ID__', id);
        document.getElementById('daftarPresensi').innerHTML = (DATA[id] || []).map(function (s) {
            const opsi = STATUS.map(x => '<option value="' + x + '"' + (x === s.status ? ' selected' : '') + '>' + x + '</option>').join('');
            return '<div class="py-1 border-bottom"><div class="d-flex justify-content-between align-items-center">'
                + '<span class="small">' + esc(s.nama) + '</span>'
                + '<select name="attendance[' + s.id + ']" class="form-select form-select-sm sel-presensi" style="width:120px;">' + opsi + '</select></div>'
                + '<input type="text" name="keterangan[' + s.id + ']" value="' + esc(s.ket || '') + '" maxlength="255" class="form-control form-control-sm mt-1 ket-presensi' + (s.status === 'Hadir' ? ' d-none' : '') + '" placeholder="Keterangan, mis. rapat OSIS"></div>';
        }).join('');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPresensi')).show();
    });
})();

// Kolom keterangan hanya tampil bila status bukan Hadir.
document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('sel-presensi')) return;
    const ket = e.target.closest('.py-1').querySelector('.ket-presensi');
    if (!ket) return;
    const hadir = e.target.value === 'Hadir';
    ket.classList.toggle('d-none', hadir);
    if (hadir) ket.value = '';
    else ket.focus();
});

// Selalu jalankan sekali di awal — baik ada preselect dari tombol alert maupun tidak,
// biar semua roster yang belum kepilih pasti ke-disable dari awal.
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('scheduleSelect');
    showRoster(sel ? sel.value : '');
});
</script>
@endpush
@endsection
