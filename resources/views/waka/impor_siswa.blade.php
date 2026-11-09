@extends('layouts.app')

@section('title', 'Impor Siswa')

@section('content')

<div class="scp-card mb-3">
    <div class="scp-card-header accent-primary">Data Siswa</div>
    <div class="scp-card-body">
        <form action="{{ route('waka.impor.pratinjau') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Kelas</label>
                    <select name="id_kelas" class="form-select" required>
                        <option value="">Pilih kelas</option>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id_kelas }}" @selected($idKelas === $k->id_kelas)>{{ $k->id_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">NIS dan Nama Lengkap</label>
                    <textarea name="teks" rows="8" class="form-control font-monospace" style="font-size:.85rem;" placeholder="Salin dua kolom dari Excel, lalu tempel di sini" required>{{ $teks }}</textarea>
                    <div class="form-text">Kata sandi awal: {{ $sandi }}</div>
                </div>
            </div>
            <div class="d-grid d-md-flex justify-content-md-end mt-3">
                <button type="submit" class="btn btn-outline-primary">Pratinjau</button>
            </div>
        </form>
    </div>
</div>

@if($hasil !== null)
@php
    $nValid = count($hasil['valid']);
    $nLewat = count($hasil['ada']) + count($hasil['ganda']) + count($hasil['rusak']);
@endphp

@if($hasil['terpotong'])
    <div class="alert alert-warning small">Hanya {{ $maks }} baris pertama yang diproses.</div>
@endif

@if($nLewat)
<div class="scp-card mb-3">
    <div class="scp-card-header accent-primary">Dilewati ({{ $nLewat }})</div>
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Baris</th><th>Data</th><th>Alasan</th></tr></thead>
                <tbody>
                @foreach($hasil['rusak'] as $r)
                    <tr><td>{{ $r['baris'] }}</td><td>{{ $r['isi'] }}</td><td class="small">{{ $r['alasan'] }}</td></tr>
                @endforeach
                @foreach($hasil['ganda'] as $r)
                    <tr><td>{{ $r['baris'] }}</td><td>{{ $r['nis'] }} &ndash; {{ $r['nama'] }}</td><td class="small">{{ $r['alasan'] }}</td></tr>
                @endforeach
                @foreach($hasil['ada'] as $r)
                    <tr><td>{{ $r['baris'] }}</td><td>{{ $r['nis'] }} &ndash; {{ $r['nama'] }}</td><td class="small">{{ $r['alasan'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($nValid)
<div class="scp-card mb-3">
    <div class="scp-card-header accent-primary">Akan Disimpan ({{ $nValid }})</div>
    <div class="scp-card-body p-0 p-md-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-paginasi="20">
                <thead class="table-light"><tr><th>NIS</th><th>Nama</th><th>Kelas</th></tr></thead>
                <tbody>
                @foreach($hasil['valid'] as $r)
                    <tr><td>{{ $r['nis'] }}</td><td>{{ $r['nama'] }}</td><td>{{ $idKelas }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<form action="{{ route('waka.impor.simpan') }}" method="POST"
      data-confirm="Simpan {{ $nValid }} siswa ke kelas {{ $idKelas }}?">
    @csrf
    <input type="hidden" name="id_kelas" value="{{ $idKelas }}">
    <textarea name="teks" class="d-none">{{ $teks }}</textarea>
    <div class="d-grid d-md-flex justify-content-md-end mb-4">
        <button type="submit" class="btn btn-primary">Simpan {{ $nValid }} Siswa</button>
    </div>
</form>
@else
<div class="text-muted small">Tidak ada baris yang bisa disimpan.</div>
@endif
@endif
@endsection
