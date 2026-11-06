@extends('layouts.app')

@section('title', 'Beranda Waka Kurikulum')
@section('subtitle', now()->translatedFormat('l, d F Y'))

@section('content')

{{-- Blok aktif & kunci jadwal: status sekaligus kontrol (klik untuk mengubah) --}}
<div class="scp-card mb-4">
    <div class="scp-card-body">
        <div class="row g-4">
            <div class="col-12 col-md-5">
                <div class="text-muted small mb-2">Blok Aktif</div>
                <div class="btn-group" role="group" aria-label="Blok aktif">
                    @foreach(['Blok 1', 'Blok 2'] as $nm)
                        @php $aktif = $config->active_cycle === $nm; @endphp
                        <form action="{{ route('waka.config.update') }}" method="POST" class="d-inline"
                              @unless($aktif) data-confirm="Ganti blok aktif ke {{ $nm }}? Beranda guru, jurnal, dan alert akan mengikuti {{ $nm }}. Jadwal {{ $nm }} otomatis dikunci dan blok lainnya dibuka." @endunless>
                            @csrf
                            <input type="hidden" name="active_cycle" value="{{ $nm }}">
                            <button type="submit" {{ $aktif ? 'disabled' : '' }}
                                    class="btn {{ $aktif ? 'btn-scp-primary' : 'btn-outline-secondary' }} px-4 fw-semibold"
                                    style="{{ $aktif ? 'opacity:1;' : '' }} {{ $loop->first ? 'border-top-right-radius:0;border-bottom-right-radius:0;' : 'border-top-left-radius:0;border-bottom-left-radius:0;' }}">
                                @if($aktif)<i class="bi bi-check2-circle me-1"></i>@endif{{ $nm }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="col-12 col-md-7">
                <div class="text-muted small mb-2">Kunci Jadwal <span class="d-none d-md-inline">(klik untuk mengunci / membuka)</span></div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(['Blok 1' => ['is_locked_blok_1', $config->is_locked_blok_1], 'Blok 2' => ['is_locked_blok_2', $config->is_locked_blok_2]] as $nm => [$field, $lk])
                        <form action="{{ route('waka.config.update') }}" method="POST"
                              data-confirm="{{ $lk ? 'Buka kunci jadwal '.$nm.'? Guru bisa menambah jadwal '.$nm.' lagi.' : 'Kunci jadwal '.$nm.'? Guru tidak bisa menambah jadwal '.$nm.' sampai dibuka lagi.' }}">
                            @csrf
                            <input type="hidden" name="{{ $field }}" value="{{ $lk ? 0 : 1 }}">
                            <button type="submit" class="btn fw-semibold px-3 {{ $lk ? 'btn-danger' : '' }}"
                                    @unless($lk) style="background:#1F8A5C; color:#fff;" @endunless
                                    title="{{ $lk ? 'Klik untuk membuka kunci' : 'Klik untuk mengunci' }}">
                                <i class="bi {{ $lk ? 'bi-lock-fill' : 'bi-unlock-fill' }} me-1"></i>
                                {{ $nm }}: {{ $lk ? 'Terkunci' : 'Terbuka' }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@if($alerts->count() > 0)
<div class="scp-card mb-4" style="border-color:#fecaca;">
    <div class="scp-card-header" style="color:#dc2626;">
        <i class="bi bi-exclamation-triangle-fill"></i> Alert: {{ $alerts->count() }} Guru Belum Mengisi Jurnal (&gt;30 menit)
    </div>
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Guru</th><th>Kelas &amp; Mapel</th><th>Jam Selesai</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach($alerts as $a)
                    <tr>
                        <td class="fw-semibold">{{ $a->guru->nama_guru ?? '-' }}</td>
                        <td>{{ $a->kelas->id_kelas ?? $a->id_kelas }} &mdash; {{ $a->mataPelajaran->nama_mapel ?? $a->id_mapel }}</td>
                        <td><span class="badge bg-danger">{{ $a->jam_selesai }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('waka.jurnal.rekap') }}" class="btn btn-sm btn-outline-danger">Lihat Rekap Jurnal</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Statistik ringkas sistem --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-people fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $stats['guru'] }}</div>
                    <div class="text-muted small">Guru &amp; Staf</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-mortarboard fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $stats['siswa'] }}</div>
                    <div class="text-muted small">Siswa Aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-door-open fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $stats['kelas'] }}</div>
                    <div class="text-muted small">Kelas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="scp-card h-100">
            <div class="scp-card-body d-flex align-items-center gap-3 py-3">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:10px;background:var(--scp-accent-soft);color:var(--scp-primary);">
                    <i class="bi bi-journal-check fs-5"></i>
                </div>
                <div>
                    <div class="fs-4 fw-semibold font-display">{{ $jurnalHariIni }}</div>
                    <div class="text-muted small">Jurnal Diisi Hari Ini</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
