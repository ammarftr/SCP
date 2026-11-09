@extends('layouts.app')

@section('title', 'Kenaikan Kelas')

@section('content')
@php
    $totalProses = $ringkas['naik'] + $ringkas['lulus'];
@endphp

@if(count($rencana) === 0)
    <div class="scp-card"><div class="scp-card-body text-center text-muted py-5">
        Belum ada siswa aktif yang punya kelas.
    </div></div>
@else
<form action="{{ route('waka.kenaikan.proses') }}" method="POST" id="formKenaikan"
      data-confirm="Proses kenaikan kelas? {{ $ringkas['naik'] }} siswa naik kelas dan {{ $ringkas['lulus'] }} siswa lulus.">
    @csrf
    <input type="hidden" name="tanda" value="{{ $tanda }}">

    <div class="scp-card mb-3">
        <div class="scp-card-header accent-primary">Pratinjau per Kelas</div>
        <div class="scp-card-body p-0 p-md-3">
            <p class="small text-muted px-3 px-md-0 pt-3 pt-md-0 mb-2">
                {{ $ringkas['naik'] }} naik kelas, {{ $ringkas['lulus'] }} lulus{{ $ringkas['tidak_dikenali'] ? ', ' . $ringkas['tidak_dikenali'] . ' tidak diproses' : '' }}
            </p>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Kelas Sekarang</th><th class="text-center">Siswa</th><th>Menjadi</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                    @foreach($rencana as $k)
                        <tr>
                            <td class="fw-semibold">{{ $k['asal'] }}</td>
                            <td class="text-center">{{ count($k['siswa']) }}</td>
                            <td>
                                @if($k['jenis'] === 'naik')
                                    {{ $k['tujuan'] }}
                                @elseif($k['jenis'] === 'lulus')
                                    Lulus
                                @else
                                    &ndash;
                                @endif
                            </td>
                            <td class="small">
                                @if($k['jenis'] === 'naik' && !$k['tujuan_ada'])
                                    Kelas tujuan dibuat otomatis
                                @elseif($k['jenis'] === 'tidak_dikenali')
                                    <span class="text-danger">Nama kelas tidak sesuai pola, tidak diproses</span>
                                @else
                                    <span class="text-muted">Siap</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="scp-card mb-3">
        <div class="scp-card-header accent-primary d-flex justify-content-between align-items-center">
            <span>Pengecualian</span>
            <span class="small text-muted" id="jumlahPengecualian">0 dicentang</span>
        </div>
        <div class="scp-card-body">
            <p class="small text-muted mb-3">Centang siswa yang tidak ikut diproses (tinggal kelas, pindah, dan sebagainya).</p>
            @foreach($rencana as $k)
                @continue($k['jenis'] === 'tidak_dikenali')
                <details class="mb-2 border rounded-3 px-3 py-2">
                    <summary class="fw-semibold" style="cursor:pointer;">{{ $k['asal'] }} <span class="small text-muted fw-normal ms-1">{{ count($k['siswa']) }} siswa</span></summary>
                    <div class="row g-1 mt-2">
                        @foreach($k['siswa'] as $s)
                            <div class="col-12 col-md-6 col-xl-4">
                                <label class="d-flex align-items-start gap-2 small py-1">
                                    <input type="checkbox" class="form-check-input mt-1 cek-pengecualian" name="tidak_ikut[]" value="{{ $s->id_siswa }}">
                                    <span>{{ $s->nama_siswa }} <span class="text-muted">({{ $s->nisn }})</span></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </div>

    <div class="d-grid d-md-flex justify-content-md-end mb-4">
        <button type="submit" class="btn btn-primary" {{ $totalProses === 0 ? 'disabled' : '' }}>Proses Kenaikan Kelas</button>
    </div>
</form>
@endif

<div class="scp-card">
    <div class="scp-card-header accent-primary">Riwayat Proses</div>
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Waktu</th><th>Oleh</th><th class="text-center">Naik</th><th class="text-center">Lulus</th><th>Status</th><th class="text-center">Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($riwayat as $r)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $r->nama_pembuat ?? '-' }}</td>
                        <td class="text-center">{{ $r->jumlah_naik }}</td>
                        <td class="text-center">{{ $r->jumlah_lulus }}</td>
                        <td>{{ $r->dibatalkan_pada ? 'Dibatalkan' : 'Selesai' }}</td>
                        <td class="text-center">
                            @if(!$r->dibatalkan_pada && (int) $r->id === (int) $idTerakhir)
                                <form action="{{ route('waka.kenaikan.batalkan', $r->id) }}" method="POST" class="d-inline"
                                      data-confirm="Batalkan proses ini? Siswa dikembalikan ke kelas semula.">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Batalkan</button>
                                </form>
                            @else
                                <span class="text-muted">&ndash;</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada proses.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var info = document.getElementById('jumlahPengecualian');
        if (!info) return;
        document.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('cek-pengecualian')) {
                info.textContent = document.querySelectorAll('.cek-pengecualian:checked').length + ' dicentang';
            }
        });
    })();
</script>
@endpush
