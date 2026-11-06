@extends('layouts.app')

@section('title', 'Manajemen Jadwal')
@section('subtitle', 'Pantau & rapikan jadwal semua kelas')

@php
    // Tautan yang mempertahankan filter lain (halaman selalu kembali ke 1)
    $url = fn(array $ubah) => route('waka.jadwal', array_filter(
        array_merge(request()->except('page'), $ubah),
        fn($v) => $v !== null && $v !== ''
    ));
    $adaFilter = $filter['hari'] || $filter['kelas'] || $filter['guru'] || $filter['ruangan'] || $filter['q'] !== '';
    $totalSemua = ($jumlahPerBlok['Blok 1'] ?? 0) + ($jumlahPerBlok['Blok 2'] ?? 0);
@endphp

@section('content')

{{-- ============ Tab Blok ============ --}}
<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" style="gap:.4rem;">
    @foreach(['Blok 1', 'Blok 2'] as $b)
        <li class="nav-item">
            <a class="nav-link text-nowrap {{ $blok === $b ? 'active' : '' }}" href="{{ $url(['blok' => $b]) }}">
                <i class="bi {{ $kunci[$b] ? 'bi-lock-fill' : 'bi-unlock' }} me-1" title="{{ $kunci[$b] ? 'Terkunci' : 'Terbuka' }}"></i>{{ $b }}
                @if($config->active_cycle === $b)<span class="badge bg-success ms-1">Aktif</span>@endif
                <span class="badge {{ $blok === $b ? 'bg-light text-dark' : 'bg-secondary' }} ms-1">{{ $jumlahPerBlok[$b] ?? 0 }}</span>
            </a>
        </li>
    @endforeach
    <li class="nav-item">
        <a class="nav-link text-nowrap {{ $blok === 'semua' ? 'active' : '' }}" href="{{ $url(['blok' => 'semua']) }}">
            Semua Blok
            <span class="badge {{ $blok === 'semua' ? 'bg-light text-dark' : 'bg-secondary' }} ms-1">{{ $totalSemua }}</span>
        </a>
    </li>
</ul>

{{-- ============ Filter ============ --}}
<div class="scp-card mb-3">
    <div class="scp-card-body">
        <form method="GET" action="{{ route('waka.jadwal') }}" id="formFilterJadwal" class="row g-2 align-items-end" data-auto-filter>
            <input type="hidden" name="blok" value="{{ $blok }}">
            <input type="hidden" name="hari" value="{{ $filter['hari'] }}">

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label small fw-semibold mb-1">Cari</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" value="{{ $filter['q'] }}" placeholder="Kelas / mapel / guru…">
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small fw-semibold mb-1">Kelas</label>
                <select name="kelas" class="form-select select-search">
                    <option value="">Semua kelas</option>
                    @foreach($kelasList as $k)
                        <option value="{{ $k->id_kelas }}" {{ $filter['kelas'] == $k->id_kelas ? 'selected' : '' }}>{{ $k->id_kelas }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-3">
                <label class="form-label small fw-semibold mb-1">Guru</label>
                <select name="guru" class="form-select select-search">
                    <option value="">Semua guru</option>
                    @foreach($guruList as $g)
                        <option value="{{ $g->id_guru }}" {{ $filter['guru'] == $g->id_guru ? 'selected' : '' }}>{{ $g->nama_guru }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small fw-semibold mb-1">Ruangan</label>
                <select name="ruangan" class="form-select select-search">
                    <option value="">Semua ruangan</option>
                    @foreach($ruanganList as $r)
                        <option value="{{ $r->id_ruangan }}" {{ $filter['ruangan'] == $r->id_ruangan ? 'selected' : '' }}>{{ $r->nama_ruangan }}</option>
                    @endforeach
                </select>
            </div>
            @if($adaFilter)
                <div class="col-6 col-md-3 col-xl-2">
                    <a href="{{ route('waka.jadwal', ['blok' => $blok]) }}" class="btn btn-outline-secondary w-100" title="Hapus semua filter">Atur ulang</a>
                </div>
            @endif
            <noscript>
                <div class="col-12"><button type="submit" class="btn btn-scp-primary">Terapkan</button></div>
            </noscript>
        </form>

        {{-- Pilihan hari --}}
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="{{ $url(['hari' => null]) }}" class="btn btn-sm {{ $filter['hari'] ? 'btn-outline-secondary' : 'btn-scp-primary' }}">Semua hari</a>
            @foreach($hariList as $h)
                <a href="{{ $url(['hari' => $h]) }}" class="btn btn-sm {{ $filter['hari'] === $h ? 'btn-scp-primary' : 'btn-outline-secondary' }}">{{ $h }}</a>
            @endforeach
        </div>
    </div>
</div>

{{-- ============ Ringkasan hasil ============ --}}
<div class="row g-2 mb-3">
    @foreach([
        ['Jadwal', $ringkas->total ?? 0, 'bi-calendar3'],
        ['Guru', $ringkas->guru ?? 0, 'bi-person-badge'],
        ['Kelas', $ringkas->kelas ?? 0, 'bi-door-open'],
        ['Ruangan', $ringkas->ruangan ?? 0, 'bi-building'],
    ] as [$label, $nilai, $ikon])
        <div class="col-6 col-md-3">
            <div class="scp-card h-100">
                <div class="scp-card-body d-flex align-items-center gap-3 py-2">
                    <i class="bi {{ $ikon }} fs-5" style="color:var(--scp-primary);"></i>
                    <div>
                        <div class="fs-5 fw-semibold font-display lh-1">{{ $nilai }}</div>
                        <div class="text-muted small">{{ $label }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($blok !== 'semua' && $kunci[$blok])
    <div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
        <i class="bi bi-lock-fill"></i>
        <span>{{ $blok }} sedang <strong>terkunci</strong>. Guru tidak bisa mengubah jadwalnya; hati-hati saat menghapus.</span>
    </div>
@endif

{{-- ============ Tabel jadwal ============ --}}
<div class="scp-card">
    <div class="scp-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            @if($jadwals->total() > 0)
                Menampilkan {{ $jadwals->firstItem() }}–{{ $jadwals->lastItem() }} dari {{ $jadwals->total() }} jadwal
            @else
                Tidak ada jadwal
            @endif
        </span>
        <form method="GET" action="{{ route('waka.jadwal') }}" class="d-flex align-items-center gap-2" data-auto-filter>
            @foreach(request()->except(['page', 'per_page']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <label class="small text-muted mb-0" for="perPage">Per halaman</label>
            <select name="per_page" id="perPage" class="form-select form-select-sm" style="width:auto;">
                @foreach([25, 50, 100] as $n)
                    <option value="{{ $n }}" {{ $perPage == $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="scp-card-body p-0">
        <div class="table-responsive tabel-jadwal-wrap">
            <table class="table table-hover table-sm align-middle mb-0 tabel-jadwal">
                <thead>
                    <tr>
                        <th class="text-nowrap">Jam</th>
                        <th>Kelas &amp; Mapel</th>
                        <th class="d-none d-md-table-cell">Guru</th>
                        <th class="d-none d-md-table-cell">Ruangan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @php $sebelumnya = null; @endphp
                @forelse($jadwals as $j)
                    @php $grup = ($blok === 'semua' ? $j->cycle_type . ' · ' : '') . $j->hari; @endphp
                    @if($grup !== $sebelumnya)
                        <tr class="pemisah-hari">
                            <td colspan="5">
                                {{ $grup }}
                                @if($blok === 'semua' && ($kunci[$j->cycle_type] ?? false))
                                    <i class="bi bi-lock-fill text-danger ms-1" title="{{ $j->cycle_type }} terkunci"></i>
                                @endif
                            </td>
                        </tr>
                        @php $sebelumnya = $grup; @endphp
                    @endif
                    @php $terkunci = $kunci[$j->cycle_type] ?? false; @endphp
                    <tr>
                        <td class="text-nowrap fw-semibold">
                            {{ substr($j->jam_mulai, 0, 5) }}<span class="text-muted fw-normal">–{{ substr($j->jam_selesai, 0, 5) }}</span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $j->kelas->id_kelas ?? $j->id_kelas }}</div>
                            <div class="small text-muted">{{ $j->mataPelajaran->nama_mapel ?? $j->id_mapel }}</div>
                            {{-- Di layar kecil, guru & ruangan ikut di bawah mapel --}}
                            <div class="small d-md-none mt-1">
                                {{ $j->guru->nama_guru ?? $j->id_guru }}
                                &middot; {{ $j->ruangan->nama_ruangan ?? $j->id_ruangan_default }}
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">{{ $j->guru->nama_guru ?? $j->id_guru }}</td>
                        <td class="d-none d-md-table-cell">{{ $j->ruangan->nama_ruangan ?? $j->id_ruangan_default }}</td>
                        <td class="text-center">
                            <form action="{{ route('master.jadwal.destroy', $j->id_jadwal) }}" method="POST" class="d-inline"
                                  data-confirm="{{ $terkunci
                                      ? 'PERINGATAN: '.$j->cycle_type.' sedang DIKUNCI. Menghapus jadwal '.($j->kelas->id_kelas ?? $j->id_kelas).' ('.$j->hari.' '.substr($j->jam_mulai,0,5).') akan mengubah jadwal yang sudah final. Lanjutkan menghapus?'
                                      : 'Hapus jadwal '.($j->kelas->id_kelas ?? $j->id_kelas).': '.($j->mataPelajaran->nama_mapel ?? $j->id_mapel).' ('.$j->hari.' '.substr($j->jam_mulai,0,5).', '.$j->cycle_type.')?' }}">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $terkunci ? 'btn-danger' : 'btn-outline-danger' }}" title="Hapus jadwal">
                                    <i class="bi {{ $terkunci ? 'bi-lock-fill' : 'bi-trash' }}"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                            @if($adaFilter)
                                Tidak ada jadwal yang cocok dengan filter.
                                <div class="mt-2"><a href="{{ route('waka.jadwal', ['blok' => $blok]) }}" class="btn btn-sm btn-outline-secondary">Atur ulang filter</a></div>
                            @else
                                Belum ada jadwal{{ $blok !== 'semua' ? ' di '.$blok : '' }}. Jadwal diinput oleh guru.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($jadwals->hasPages())
        <div class="scp-card-body d-flex justify-content-center border-top pb-2">
            {{ $jadwals->links() }}
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    /* Daftar panjang tetap nyaman: header tabel menempel saat menggulir */
    .tabel-jadwal-wrap { max-height: 68vh; overflow: auto; }
    .tabel-jadwal thead th {
        position: sticky; top: 0; z-index: 2;
        background: #F3F5F8; box-shadow: inset 0 -1px 0 #dee2e6;
    }
    /* Pemisah per hari (dan blok, saat melihat "Semua Blok") */
    .tabel-jadwal tr.pemisah-hari > td {
        background: var(--scp-accent-soft);
        color: var(--scp-primary-dark);
        font-weight: 700; font-size: .85rem;
        border-left: 4px solid var(--scp-accent);
    }
</style>
@endpush

