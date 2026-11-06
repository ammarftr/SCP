@extends('layouts.app')

@section('title', 'Rekap Jurnal Guru')
@section('subtitle', $tampilkanRiwayat ? 'Menampilkan semua riwayat' : 'Jurnal hari ini, ' . now()->translatedFormat('d F Y'))

@section('content')
<div class="scp-card mb-3">
    <div class="scp-card-body">
        <form method="GET" action="{{ route('waka.jurnal.rekap') }}" class="row g-2 align-items-center" data-auto-filter>
            <div class="col-sm-6 col-md-4">
                <input type="text" name="q" value="{{ $keyword }}" class="form-control" placeholder="Cari nama guru / kelas / mapel...">
            </div>
            <noscript>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Cari</button></div>
            </noscript>
            @if($tampilkanRiwayat)
                <input type="hidden" name="riwayat" value="1">
            @endif
            <div class="col-auto ms-auto">
                @if($tampilkanRiwayat)
                    <a href="{{ route('waka.jurnal.rekap', ['q' => $keyword]) }}" class="btn btn-scp-primary">
                        Lihat Hari Ini Saja
                    </a>
                @else
                    <a href="{{ route('waka.jurnal.rekap', ['riwayat' => 1, 'q' => $keyword]) }}" class="btn btn-outline-dark">
                        Lihat Semua Riwayat
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="scp-card">
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">No.</th>
                        <th>Tanggal</th>
                        <th>Nama Guru</th>
                        <th>Kelas &amp; Mapel</th>
                        <th>Topik / Materi</th>
                        <th class="text-center">Foto KBM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jurnalList as $jurnal)
                        <tr>
                            <td class="text-center">{{ $jurnalList->firstItem() + $loop->index }}</td>
                            <td>{{ \Carbon\Carbon::parse($jurnal->date)->translatedFormat('d M Y') }}</td>
                            <td>{{ $jurnal->jadwal->guru->nama_guru ?? 'Guru Tidak Ditemukan' }}</td>
                            <td>
                                <strong>{{ $jurnal->jadwal->kelas->id_kelas ?? '-' }}</strong><br>
                                <small class="text-muted">{{ $jurnal->jadwal->mataPelajaran->nama_mapel ?? '-' }}</small>
                            </td>
                            <td>{{ $jurnal->topic_description }}</td>
                            <td class="text-center">
                                @if($jurnal->photo_path)
                                    <a href="{{ route('berkas.jurnal', $jurnal->id) }}" target="_blank" class="btn btn-sm btn-info text-white">Lihat Foto</a>
                                @else
                                    <span class="badge bg-secondary">Tidak ada</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ $tampilkanRiwayat ? 'Belum ada riwayat jurnal sama sekali.' : 'Belum ada jurnal yang diisi hari ini.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.paginasi', ['paginator' => $jurnalList, 'label' => 'jurnal'])
    </div>
</div>
@endsection
