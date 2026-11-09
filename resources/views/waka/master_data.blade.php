@extends('layouts.app')

@section('title', 'Manajemen Data Master')

@section('content')
<ul class="nav nav-pills mb-3 flex-nowrap overflow-auto" id="masterTab" role="tablist" style="gap:.4rem;">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-akun" type="button">Akun</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-kelas" type="button">Kelas</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-mapel" type="button">Mata Pelajaran</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-ruangan" type="button">Ruangan</button></li>
</ul>

<div class="tab-content">

    {{-- ============ TAB AKUN ============ --}}
    <div class="tab-pane fade show active" id="tab-akun">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="scp-card">
                    <div class="scp-card-header accent-primary">Buat Akun Baru</div>
                    <div class="scp-card-body">
                        <form action="{{ route('akun.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">NIS / NIP</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">No HP</label>
                                <input type="text" name="no_hp" class="form-control" maxlength="20" inputmode="tel" placeholder="Opsional, contoh: 081234567890">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Kata Sandi</label>
                                <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimal 8 karakter">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Peran</label>
                                <select name="id_role" id="roleSelectCreate" class="form-select" required onchange="toggleKelasField(this.value, 'kelasFieldCreate'); toggleWaliField(this.value, 'waliFieldCreate')">
                                    <option value="">-- Pilih Peran --</option>
                                    <option value="4">Waka Kurikulum</option>
                                    <option value="2">Guru</option>
                                    <option value="7">Guru BK</option>
                                    <option value="3">Wali Kelas</option>
                                    <option value="5">Siswa</option>
                                </select>
                            </div>
                            <div class="mb-3 d-none" id="kelasFieldCreate">
                                <label class="form-label small fw-semibold">Kelas (khusus Siswa)</label>
                                <select name="id_kelas" class="form-select select-search">
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach($kelas as $k)
                                        <option value="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3 d-none" id="waliFieldCreate">
                                <label class="form-label small fw-semibold">Kelas Perwalian (Wali Kelas, atau Guru yang merangkap wali kelas)</label>
                                <select name="id_kelas_wali" class="form-select">
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach($kelas as $k)
                                        <option value="{{ $k->id_kelas }}" {{ isset($waliPerKelas[$k->id_kelas]) ? 'disabled' : '' }}>{{ $k->id_kelas }}{{ isset($waliPerKelas[$k->id_kelas]) ? ' (wali: '.$waliPerKelas[$k->id_kelas].')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-scp-accent w-100">Simpan Akun</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="scp-card">
                    <div class="scp-card-header">Daftar Akun ({{ $users->count() - $akunAlumni->count() }})</div>
                    <div class="scp-card-body">
                        <input type="text" class="form-control mb-3 akun-search" placeholder="Cari nama atau nama pengguna...">

                        <ul class="nav nav-underline mb-3" role="tablist">
                            @php
                                $roleGroups = [4=>'Waka Kurikulum', 2=>'Guru', 7=>'Guru BK', 3=>'Wali Kelas', 5=>'Siswa'];
                                $usersByRole = collect($akunPerTab);
                            @endphp
                            @foreach($roleGroups as $rid => $rname)
                                <li class="nav-item">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#akun-role-{{ $rid }}" type="button">
                                        {{ $rname }} <span class="badge bg-light text-dark">{{ $usersByRole->get($rid, collect())->count() }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content">
                        @foreach($roleGroups as $rid => $rname)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="akun-role-{{ $rid }}">
                                <div class="massal" data-form-hapus="#formHapusAkun" data-modal-ubah="#modalUbahAkun" @if($rid != 5) data-ubah-fungsi="ubahAkunTerpisah" @endif data-nama="akun" data-peringatan="Data guru/siswa terkait ikut terhapus.">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">@if($rid == 5)<select class="form-select form-select-sm massal-filter w-auto" data-atribut="kelas"><option value="">Semua kelas</option>@foreach($kelas as $k)<option value="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>@endforeach</select>@endif<span class="massal-jumlah small text-muted ms-auto"></span><button type="button" class="btn btn-sm btn-outline-secondary massal-tombol" data-aksi="ubah" disabled><i class="bi bi-pencil-square me-1"></i>Ubah</button><button type="button" class="btn btn-sm btn-outline-danger massal-tombol" data-aksi="hapus" disabled><i class="bi bi-trash me-1"></i>Hapus</button></div>
<div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" data-paginasi="20">
                                        <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input massal-semua" aria-label="Pilih semua"></th><th>NIS / NIP</th><th>Nama</th><th class="text-center">Aksi</th></tr></thead>
                                        <tbody>
                                        @forelse($usersByRole->get($rid, collect()) as $u)
                                            <tr class="akun-row massal-baris" data-kelas="{{ $kelasSiswa[$u->username] ?? '' }}">
                                                <td style="width:36px"><input type="checkbox" class="form-check-input massal-cek" value="{{ $u->id_user }}"></td>
                                                <td class="akun-username">{{ $u->username }}</td>
                                                <td class="akun-nama">{{ $u->nama_lengkap }}@if($u->id_role==3 || $u->id_kelas_wali) <span class="badge bg-light text-dark border ms-1">Wali {{ $u->id_kelas_wali ?: '(belum ada kelas)' }}</span>@endif @if($u->id_role==3 && $rid==2) <span class="badge bg-light text-dark border ms-1">Mengajar</span>@elseif($u->id_role==7 && $rid==2) <span class="badge bg-light text-dark border ms-1">BK</span>@elseif($u->id_role==2 && $rid==3) <span class="badge bg-light text-dark border ms-1">Guru</span>@elseif($u->id_role==7 && $rid==3) <span class="badge bg-light text-dark border ms-1">BK</span>@endif</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-akun" data-bs-toggle="modal" data-bs-target="#editAkunModal"
                                                        data-action="{{ route('akun.update', $u->id_user) }}"
                                                        data-username="{{ $u->username }}"
                                                        data-nama="{{ $u->nama_lengkap }}"
                                                        data-hp="{{ $u->no_hp }}"
                                                        data-role="{{ $u->id_role }}"
                                                        data-kelas="{{ $kelasSiswa[$u->username] ?? '' }}"
                                                        data-wali="{{ $u->id_kelas_wali }}"><i class="bi bi-pencil"></i></button>
                                                    <form action="{{ route('akun.destroy', $u->id_user) }}" method="POST" class="d-inline" data-confirm="Hapus akun {{ $u->username }}? Data guru/siswa terkait juga ikut terhapus.">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>

                                        @empty
                                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada akun {{ $rname }}.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                            </div>
                        @endforeach
                        </div>

                        @if($akunAlumni->count())
                        <details class="mt-3 border rounded-3 px-3 py-2">
                            <summary class="fw-semibold" style="cursor:pointer;">Akun Alumni <span class="small text-muted fw-normal ms-1">{{ $akunAlumni->count() }}</span></summary>
                            <div class="mb-2"></div>
                            <div class="massal" data-form-hapus="#formHapusAkun" data-nama="akun alumni" data-peringatan="Data siswa dan riwayat presensinya ikut terhapus dan tidak bisa dikembalikan.">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="massal-jumlah small text-muted ms-auto"></span>
                                <button type="button" class="btn btn-sm btn-outline-danger massal-tombol" data-aksi="hapus" disabled>Hapus yang dipilih</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" data-paginasi="20">
                                    <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input massal-semua" aria-label="Pilih semua alumni"></th><th>NIS / NIP</th><th>Nama</th><th class="text-center">Aksi</th></tr></thead>
                                    <tbody>
                                    @foreach($akunAlumni as $u)
                                        <tr class="massal-baris">
                                            <td style="width:36px"><input type="checkbox" class="form-check-input massal-cek" value="{{ $u->id_user }}"></td>
                                            <td>{{ $u->username }}</td>
                                            <td>{{ $u->nama_lengkap }}</td>
                                            <td class="text-center">
                                                <form action="{{ route('akun.destroy', $u->id_user) }}" method="POST" class="d-inline" data-confirm="Hapus akun alumni {{ $u->username }}? Data siswa dan riwayat presensinya ikut terhapus.">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </details>
                        @endif

                        {{-- Satu modal ubah akun dipakai bersama (diisi lewat JS dari data-* tombol). Sebelumnya satu modal + 2 dropdown
                             Tom Select dibuat untuk SETIAP akun sehingga halaman ini sangat berat saat akun ratusan. --}}
                        <div class="modal fade" id="editAkunModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form id="editAkunForm" method="POST" action="">
                                        @csrf
                                        <div class="modal-header">
                                            <h6 class="modal-title fw-bold">Ubah Akun: <span id="editAkunUsername"></span></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Nama Lengkap</label>
                                                <input type="text" name="nama_lengkap" id="editAkunNama" class="form-control" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">No HP</label>
                                                <input type="text" name="no_hp" id="editAkunHp" class="form-control" maxlength="20" inputmode="tel" placeholder="Opsional">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Peran</label>
                                                <select name="id_role" id="editAkunRole" class="form-select" required onchange="toggleKelasField(this.value, 'editKelasField'); toggleWaliField(this.value, 'editWaliField')">
                                                    <option value="4">Waka Kurikulum</option>
                                                    <option value="2">Guru</option>
                                                    <option value="7">Guru BK</option>
                                                    <option value="3">Wali Kelas</option>
                                                    <option value="5">Siswa</option>
                                                </select>
                                            </div>
                                            <div class="mb-3 d-none" id="editKelasField">
                                                <label class="form-label small fw-semibold">Kelas (khusus Siswa)</label>
                                                <select name="id_kelas" id="editAkunKelas" class="form-select select-search">
                                                    <option value="">-- Pilih Kelas --</option>
                                                    @foreach($kelas as $k)
                                                        <option value="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-3 d-none" id="editWaliField">
                                                <label class="form-label small fw-semibold">Kelas Perwalian (Wali Kelas, atau Guru yang merangkap wali kelas)</label>
                                                <select name="id_kelas_wali" id="editAkunWali" class="form-select">
                                                    <option value="">-- Pilih Kelas --</option>
                                                    @foreach($kelas as $k)
                                                        <option value="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mb-1">
                                                <label class="form-label small fw-semibold">Kata Sandi Baru (opsional)</label>
                                                <input type="password" name="password" id="editAkunPassword" class="form-control" minlength="8" placeholder="Kosongkan jika tidak diubah (min. 8 karakter)">
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ TAB KELAS ============ --}}
    <div class="tab-pane fade" id="tab-kelas">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="scp-card">
                    <div class="scp-card-header accent-primary">Tambah Kelas</div>
                    <div class="scp-card-body">
                        <form action="{{ route('master.kelas.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Tingkat</label>
                                <select name="tingkat" id="tingkatSelect" class="form-select" required onchange="updateKelasPreview()">
                                    <option value="X">X</option><option value="XI">XI</option><option value="XII">XII</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Jurusan</label>
                                <select name="id_jurusan" id="jurusanSelect" class="form-select" onchange="updateKelasPreview()">
                                    @foreach($jurusan as $j)
                                        <option value="{{ $j->id_jurusan }}">{{ $j->nama_jurusan }} ({{ $j->id_jurusan }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Nama / ID Kelas</label>
                                <input type="text" name="id_kelas" id="idKelasInput" class="form-control" required placeholder='Contoh: "X RPL 1"'>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Guru BK Pengampu</label>
                                <select name="id_bk" class="form-select">
                                    <option value="">-- Belum ditetapkan --</option>
                                    @foreach($daftarBk as $bk)<option value="{{ $bk->id_user }}">{{ $bk->nama_lengkap }}</option>@endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-scp-accent w-100">Simpan Kelas</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="scp-card">
                    <div class="scp-card-header">Daftar Kelas ({{ $kelas->count() }})</div>
                    <div class="scp-card-body">
                        <input type="text" class="form-control mb-3 kelas-search" placeholder="Cari kelas...">
                        <div class="massal" data-form-hapus="#formHapusKelas" data-modal-ubah="#modalUbahKelas" data-nama="kelas" data-peringatan="Siswa dilepas dari kelas; jadwal, jurnal, dan presensi kelas ikut terhapus.">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><select class="form-select form-select-sm massal-filter w-auto" data-atribut="tingkat"><option value="">Semua tingkat</option><option>X</option><option>XI</option><option>XII</option></select><select class="form-select form-select-sm massal-filter w-auto" data-atribut="jurusan"><option value="">Semua jurusan</option>@foreach($jurusan as $j)<option value="{{ $j->id_jurusan }}">{{ $j->id_jurusan }}</option>@endforeach<option value="-">Tanpa jurusan</option></select><span class="massal-jumlah small text-muted ms-auto"></span><button type="button" class="btn btn-sm btn-outline-secondary massal-tombol" data-aksi="ubah" disabled><i class="bi bi-pencil-square me-1"></i>Ubah</button><button type="button" class="btn btn-sm btn-outline-danger massal-tombol" data-aksi="hapus" disabled><i class="bi bi-trash me-1"></i>Hapus</button></div>
<div class="table-responsive" style="max-height:480px;overflow-y:auto;">
                            <table class="table table-hover align-middle mb-0" data-paginasi="15">
                                <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input massal-semua" aria-label="Pilih semua"></th><th>Kelas</th><th>Tingkat</th><th>Jurusan</th><th>Guru BK</th><th class="text-center">Jml Siswa</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                @forelse($kelas->sortBy('id_kelas') as $k)
                                    <tr class="kelas-row massal-baris" data-tingkat="{{ $k->tingkat }}" data-jurusan="{{ $k->id_jurusan ?: '-' }}">
                                        <td style="width:36px"><input type="checkbox" class="form-check-input massal-cek" value="{{ $k->id_kelas }}"></td>
                                        <td class="kelas-nama fw-semibold">{{ $k->id_kelas }}</td>
                                        <td>{{ $k->tingkat }}</td>
                                        <td>{{ $k->id_jurusan ?? '-' }}</td>
                                        <td>@if($k->id_bk && isset($namaBk[$k->id_bk])){{ $namaBk[$k->id_bk] }}@else<span class="text-muted small">Belum ditetapkan</span>@endif</td>
                                        <td class="text-center"><span class="badge bg-secondary">{{ $jumlahSiswaPerKelas[$k->id_kelas] ?? 0 }}</span></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editKelas{{ $loop->index }}"><i class="bi bi-pencil"></i></button>
                                            <form action="{{ route('master.kelas.destroy', $k->id_kelas) }}" method="POST" class="d-inline" data-confirm="Hapus kelas {{ $k->id_kelas }}?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editKelas{{ $loop->index }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('master.kelas.update', $k->id_kelas) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Ubah Kelas: {{ $k->id_kelas }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Tingkat</label>
                                                            <select name="tingkat" class="form-select" required>
                                                                <option value="X" {{ $k->tingkat=='X'?'selected':'' }}>X</option>
                                                                <option value="XI" {{ $k->tingkat=='XI'?'selected':'' }}>XI</option>
                                                                <option value="XII" {{ $k->tingkat=='XII'?'selected':'' }}>XII</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-1">
                                                            <label class="form-label small fw-semibold">Jurusan</label>
                                                            <select name="id_jurusan" class="form-select">
                                                                <option value="">-- Tanpa Jurusan --</option>
                                                                @foreach($jurusan as $j)
                                                                    <option value="{{ $j->id_jurusan }}" {{ $k->id_jurusan==$j->id_jurusan?'selected':'' }}>{{ $j->nama_jurusan }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="mt-3">
                                                            <label class="form-label small fw-semibold">Guru BK Pengampu</label>
                                                            <select name="id_bk" class="form-select">
                                                                <option value="">-- Belum ditetapkan --</option>
                                                                @foreach($daftarBk as $bk)
                                                                    <option value="{{ $bk->id_user }}" {{ (int) $k->id_bk === (int) $bk->id_user ? 'selected' : '' }}>{{ $bk->nama_lengkap }}</option>
                                                                @endforeach
                                                            </select>
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
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data kelas.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ TAB MATA PELAJARAN ============ --}}
    <div class="tab-pane fade" id="tab-mapel">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="scp-card">
                    <div class="scp-card-header accent-primary">Tambah Mata Pelajaran</div>
                    <div class="scp-card-body">
                        <form action="{{ route('master.mapel.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Kode Mapel</label>
                                <input type="text" name="id_mapel" class="form-control" required placeholder="Contoh: BING">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Nama Mapel</label>
                                <input type="text" name="nama_mapel" class="form-control" required placeholder="Contoh: Bahasa Inggris">
                            </div>
                            <button type="submit" class="btn btn-scp-accent w-100">Simpan Mapel</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="scp-card">
                    <div class="scp-card-header">Daftar Mata Pelajaran ({{ $mapels->count() }})</div>
                    <div class="scp-card-body">
                        <input type="text" class="form-control mb-3 mapel-search" placeholder="Cari mata pelajaran...">
                        <div class="massal" data-form-hapus="#formHapusMapel"  data-nama="mata pelajaran" data-peringatan="Jadwal, jurnal, dan presensi mapel ini ikut terhapus.">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><span class="massal-jumlah small text-muted ms-auto"></span><button type="button" class="btn btn-sm btn-outline-danger massal-tombol" data-aksi="hapus" disabled><i class="bi bi-trash me-1"></i>Hapus</button></div>
<div class="table-responsive" style="max-height:480px;overflow-y:auto;">
                            <table class="table table-hover align-middle mb-0" data-paginasi="15">
                                <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input massal-semua" aria-label="Pilih semua"></th><th>Kode</th><th>Nama Mapel</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                @forelse($mapels->sortBy('nama_mapel') as $m)
                                    <tr class="mapel-row massal-baris">
                                        <td style="width:36px"><input type="checkbox" class="form-check-input massal-cek" value="{{ $m->id_mapel }}"></td>
                                        <td>{{ $m->id_mapel }}</td>
                                        <td class="mapel-nama">{{ $m->nama_mapel }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editMapel{{ $loop->index }}"><i class="bi bi-pencil"></i></button>
                                            <form action="{{ route('master.mapel.destroy', $m->id_mapel) }}" method="POST" class="d-inline" data-confirm="Hapus mapel {{ $m->nama_mapel }}?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editMapel{{ $loop->index }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('master.mapel.update', $m->id_mapel) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Ubah Mapel: {{ $m->id_mapel }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label small fw-semibold">Nama Mapel</label>
                                                        <input type="text" name="nama_mapel" class="form-control" value="{{ $m->nama_mapel }}" required>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-scp-primary">Simpan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data mapel.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ TAB RUANGAN ============ --}}
    <div class="tab-pane fade" id="tab-ruangan">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="scp-card">
                    <div class="scp-card-header accent-primary">Tambah Ruangan</div>
                    <div class="scp-card-body">
                        <form action="{{ route('master.ruangan.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">ID Ruangan</label>
                                <input type="text" name="id_ruangan" class="form-control" required placeholder="Contoh: 35">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Nama Ruangan</label>
                                <input type="text" name="nama_ruangan" class="form-control" required placeholder="Contoh: R 35">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Jenis</label>
                                <select name="jenis" class="form-select">
                                    <option value="Ruangan">Ruangan Teori</option>
                                    <option value="Lab">Laboratorium</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-scp-accent w-100">Simpan Ruangan</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="scp-card">
                    <div class="scp-card-header">Daftar Ruangan ({{ $ruangans->count() }})</div>
                    <div class="scp-card-body">
                        <input type="text" class="form-control mb-3 ruangan-search" placeholder="Cari ruangan...">
                        <div class="massal" data-form-hapus="#formHapusRuangan" data-modal-ubah="#modalUbahRuangan" data-nama="ruangan" data-peringatan="Jadwal, jurnal, dan presensi di ruangan ini ikut terhapus.">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><select class="form-select form-select-sm massal-filter w-auto" data-atribut="jenis"><option value="">Semua jenis</option><option value="Ruangan">Ruangan Teori</option><option value="Lab">Laboratorium</option></select><span class="massal-jumlah small text-muted ms-auto"></span><button type="button" class="btn btn-sm btn-outline-secondary massal-tombol" data-aksi="ubah" disabled><i class="bi bi-pencil-square me-1"></i>Ubah</button><button type="button" class="btn btn-sm btn-outline-danger massal-tombol" data-aksi="hapus" disabled><i class="bi bi-trash me-1"></i>Hapus</button></div>
<div class="table-responsive" style="max-height:480px;overflow-y:auto;">
                            <table class="table table-hover align-middle mb-0" data-paginasi="15">
                                <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input massal-semua" aria-label="Pilih semua"></th><th>ID</th><th>Nama Ruangan</th><th>Jenis</th><th class="text-center">Aksi</th></tr></thead>
                                <tbody>
                                @forelse($ruangans->sortBy('nama_ruangan') as $r)
                                    <tr class="ruangan-row massal-baris" data-jenis="{{ $r->jenis ?? 'Ruangan' }}">
                                        <td style="width:36px"><input type="checkbox" class="form-check-input massal-cek" value="{{ $r->id_ruangan }}"></td>
                                        <td>{{ $r->id_ruangan }}</td>
                                        <td class="ruangan-nama">{{ $r->nama_ruangan }}</td>
                                        <td><span class="badge bg-{{ $r->jenis=='Lab'?'info':'secondary' }}">{{ $r->jenis ?? 'Ruangan' }}</span></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editRuangan{{ $loop->index }}"><i class="bi bi-pencil"></i></button>
                                            <form action="{{ route('master.ruangan.destroy', $r->id_ruangan) }}" method="POST" class="d-inline" data-confirm="Hapus ruangan {{ $r->nama_ruangan }}?">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editRuangan{{ $loop->index }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('master.ruangan.update', $r->id_ruangan) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Ubah Ruangan: {{ $r->id_ruangan }}</h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Nama Ruangan</label>
                                                            <input type="text" name="nama_ruangan" class="form-control" value="{{ $r->nama_ruangan }}" required>
                                                        </div>
                                                        <div class="mb-1">
                                                            <label class="form-label small fw-semibold">Jenis</label>
                                                            <select name="jenis" class="form-select">
                                                                <option value="Ruangan" {{ $r->jenis=='Ruangan'?'selected':'' }}>Ruangan Teori</option>
                                                                <option value="Lab" {{ $r->jenis=='Lab'?'selected':'' }}>Laboratorium</option>
                                                            </select>
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
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data ruangan.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<form id="formHapusAkun" method="POST" action="{{ route('master.akun.massal-hapus') }}" class="d-none">@csrf<div class="massal-ids"></div></form>
<form id="formHapusKelas" method="POST" action="{{ route('master.kelas.massal-hapus') }}" class="d-none">@csrf<div class="massal-ids"></div></form>
<form id="formHapusMapel" method="POST" action="{{ route('master.mapel.massal-hapus') }}" class="d-none">@csrf<div class="massal-ids"></div></form>
<form id="formHapusRuangan" method="POST" action="{{ route('master.ruangan.massal-hapus') }}" class="d-none">@csrf<div class="massal-ids"></div></form>
<div class="modal fade" id="modalUbahAkun" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="{{ route('master.akun.massal-ubah') }}">
@csrf<div class="massal-ids"></div>
<div class="modal-header"><h6 class="modal-title fw-bold">Ubah Akun (<span class="massal-n">0</span> dipilih)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="mb-3"><label class="form-label small fw-semibold">Pindah ke kelas (khusus akun Siswa)</label><select name="id_kelas" class="form-select"><option value="">-- Tidak diubah --</option>@foreach($kelas as $k)<option value="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label small fw-semibold">Kata sandi baru</label><input type="password" name="password" class="form-control" minlength="8" placeholder="Minimal 8 karakter" autocomplete="new-password"></div><small class="text-muted">Kolom yang dikosongkan tidak diubah.</small></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-scp-primary">Simpan</button></div>
</form></div></div></div>
<style>
    #modalUbahAkunTerpisah option:disabled, #editAkunModal option:disabled { color:#adb5bd; background:#f1f3f5; }
    /* Form dibungkus <form>, jadi Bootstrap 'scrollable' tidak bekerja sendiri: bungkus dibuat flex supaya isi bisa di-scroll dan tombol Simpan tetap terlihat */
    #modalUbahAkunTerpisah .modal-content { max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); }
    #modalUbahAkunTerpisah .modal-content > form { display:flex; flex-direction:column; flex:1 1 auto; min-height:0; max-height:inherit; overflow:hidden; }
    #modalUbahAkunTerpisah .modal-body { flex:1 1 auto; min-height:0; overflow-y:auto; -webkit-overflow-scrolling:touch; }
    #modalUbahAkunTerpisah .modal-header, #modalUbahAkunTerpisah .modal-footer { flex:0 0 auto; }
    .ubah-kartu { border:1px solid #E9ECF1; border-radius:10px; padding:.85rem; margin-bottom:.85rem; background:#fff; }
    .ubah-kartu .ubah-judul { font-size:.8rem; font-weight:700; color:#555; margin-bottom:.6rem; }
</style>
<template id="tplUbahAkunTerpisah">
<div class="ubah-kartu" data-id="__ID__">
    <div class="ubah-judul"><span class="ubah-no"></span> &middot; <span class="ubah-username text-muted"></span></div>
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label small fw-semibold">Nama Lengkap</label><input type="text" name="akun[__ID__][nama_lengkap]" class="form-control form-control-sm f-nama" required maxlength="100"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">No HP</label><input type="text" name="akun[__ID__][no_hp]" class="form-control form-control-sm f-hp" maxlength="20" inputmode="tel" placeholder="Opsional"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Peran</label>
            <select name="akun[__ID__][id_role]" class="form-select form-select-sm f-role" required>
                <option value="4">Waka Kurikulum</option><option value="2">Guru</option><option value="7">Guru BK</option><option value="3">Wali Kelas</option>
            </select></div>
        <div class="col-md-4 f-wali-wrap"><label class="form-label small fw-semibold">Kelas Perwalian</label>
            <select name="akun[__ID__][id_kelas_wali]" class="form-select form-select-sm f-wali">
                <option value="">-- Bukan wali kelas --</option>
                @foreach($kelas as $k)<option value="{{ $k->id_kelas }}" data-kelas="{{ $k->id_kelas }}">{{ $k->id_kelas }}</option>@endforeach
            </select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Kata Sandi Baru</label><input type="password" name="akun[__ID__][password]" class="form-control form-control-sm" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tetap"></div>
    </div>
</div>
</template>
<div class="modal fade" id="modalUbahAkunTerpisah" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
<form method="POST" action="{{ route('master.akun.massal-ubah-terpisah') }}">
@csrf
<div class="modal-header"><h6 class="modal-title fw-bold">Ubah Akun (<span id="ubahTerpisahJumlah">0</span> dipilih)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Setiap akun punya isian sendiri. Kelas yang sudah punya wali (atau sudah dipilih untuk akun lain di sini) berwarna abu-abu dan tidak bisa dipilih.</div>
    <div id="ubahTerpisahDaftar"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-scp-primary">Simpan Semua</button></div>
</form></div></div></div>
<div class="modal fade" id="modalUbahKelas" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="{{ route('master.kelas.massal-ubah') }}">
@csrf<div class="massal-ids"></div>
<div class="modal-header"><h6 class="modal-title fw-bold">Ubah Kelas (<span class="massal-n">0</span> dipilih)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="mb-3"><label class="form-label small fw-semibold">Tingkat</label><select name="tingkat" class="form-select"><option value="">-- Tidak diubah --</option><option>X</option><option>XI</option><option>XII</option></select></div><div class="mb-3"><label class="form-label small fw-semibold">Jurusan</label><select name="id_jurusan" class="form-select"><option value="">-- Tidak diubah --</option><option value="-">Tanpa jurusan</option>@foreach($jurusan as $j)<option value="{{ $j->id_jurusan }}">{{ $j->nama_jurusan }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label small fw-semibold">Guru BK Pengampu</label><select name="id_bk" class="form-select"><option value="">-- Tidak diubah --</option><option value="-">Kosongkan (belum ditetapkan)</option>@foreach($daftarBk as $bk)<option value="{{ $bk->id_user }}">{{ $bk->nama_lengkap }}</option>@endforeach</select></div><small class="text-muted">Kolom yang dikosongkan tidak diubah.</small></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-scp-primary">Simpan</button></div>
</form></div></div></div>
<div class="modal fade" id="modalUbahRuangan" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="POST" action="{{ route('master.ruangan.massal-ubah') }}">
@csrf<div class="massal-ids"></div>
<div class="modal-header"><h6 class="modal-title fw-bold">Ubah Ruangan (<span class="massal-n">0</span> dipilih)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="mb-3"><label class="form-label small fw-semibold">Jenis</label><select name="jenis" class="form-select" required><option value="">-- Pilih --</option><option value="Ruangan">Ruangan Teori</option><option value="Lab">Laboratorium</option></select></div><small class="text-muted">Kolom yang dikosongkan tidak diubah.</small></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-scp-primary">Simpan</button></div>
</form></div></div></div>

@push('scripts')
<script>
function toggleKelasField(role, fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.toggle('d-none', role != '5');
}

function toggleWaliField(role, fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    field.classList.toggle('d-none', !['2', '3', '7'].includes(String(role)));
}

function updateKelasPreview() {
    const tingkat = document.getElementById('tingkatSelect').value;
    const jurusanText = document.getElementById('jurusanSelect').options[document.getElementById('jurusanSelect').selectedIndex]?.text.match(/\(([^)]+)\)/);
    const jurusan = jurusanText ? jurusanText[1] : '';
    const input = document.getElementById('idKelasInput');
    if (!input.dataset.userEdited) {
        input.value = `${tingkat} ${jurusan} `.trim();
    }
}
document.getElementById('idKelasInput')?.addEventListener('input', function () {
    this.dataset.userEdited = 'true';
});

// Live search — simple client-side filter, tidak perlu reload halaman
function attachSearch(inputClass, rowClass, textClasses) {
    document.querySelectorAll('.' + inputClass).forEach(function (input) {
        input.addEventListener('keyup', function () {
            const keyword = this.value.toLowerCase();
            document.querySelectorAll('.' + rowClass).forEach(function (row) {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(keyword) ? '' : 'none';
            });
            if (window.massalSegarkan) window.massalSegarkan();
        });
    });
}

// Modal ubah akun bersama: isi dari data-* tombol yang diklik
const WALI_PER_KELAS = @json($waliPerKelas);
document.getElementById('editAkunModal')?.addEventListener('show.bs.modal', function (ev) {
    const b = ev.relatedTarget;
    if (!b || !b.classList.contains('btn-edit-akun')) return;
    const d = b.dataset;
    document.getElementById('editAkunForm').action = d.action;
    document.getElementById('editAkunUsername').textContent = d.username;
    document.getElementById('editAkunNama').value = d.nama || '';
    document.getElementById('editAkunHp').value = d.hp || '';
    document.getElementById('editAkunRole').value = d.role;
    document.getElementById('editAkunPassword').value = '';
    toggleKelasField(d.role, 'editKelasField');
    toggleWaliField(d.role, 'editWaliField');

    const kelasSel = document.getElementById('editAkunKelas');
    const waliSel = document.getElementById('editAkunWali');
    // Kelas yang sudah punya wali lain dinonaktifkan (kecuali milik akun ini)
    const waliOpts = Array.from(waliSel.options).filter(o => o.value !== '');
    if (waliSel.tomselect) {
        const ts = waliSel.tomselect;
        waliOpts.forEach(o => {
            const lain = WALI_PER_KELAS[o.value] !== undefined && d.wali !== o.value;
            ts.updateOption(o.value, { value: o.value, text: o.value + (lain ? ' (wali: ' + WALI_PER_KELAS[o.value] + ')' : ''), disabled: lain });
        });
        ts.refreshOptions(false);
        ts.setValue(d.wali || '', true);
    } else {
        waliOpts.forEach(o => {
            const lain = WALI_PER_KELAS[o.value] !== undefined && d.wali !== o.value;
            o.textContent = o.value + (lain ? ' (wali: ' + WALI_PER_KELAS[o.value] + ')' : '');
            o.disabled = lain;
        });
        waliSel.value = d.wali || '';
    }
    if (kelasSel.tomselect) kelasSel.tomselect.setValue(d.kelas || '', true); else kelasSel.value = d.kelas || '';
});

// ===== Ubah akun terpisah (tiap akun punya form sendiri) =====
const WALI_ID_PER_KELAS = @json($waliIdPerKelas);
function peranBisaWali(r) { return ['2', '3', '7'].includes(String(r)); }
function nilaiWali(k) { return peranBisaWali(k.querySelector('.f-role').value) ? k.querySelector('.f-wali').value : ''; }

function segarkanWaliTerpisah() {
    const kartu = Array.from(document.querySelectorAll('#ubahTerpisahDaftar .ubah-kartu'));
    const idBatch = new Set(kartu.map(k => k.dataset.id));
    const dipilih = {};   // kelas -> {id, nama}
    kartu.forEach(k => { const v = nilaiWali(k); if (v) dipilih[v] = { id: k.dataset.id, nama: k.querySelector('.f-nama').value || 'akun lain' }; });

    kartu.forEach(k => {
        k.querySelector('.f-wali-wrap').classList.toggle('d-none', !peranBisaWali(k.querySelector('.f-role').value));
        k.querySelectorAll('.f-wali option[data-kelas]').forEach(o => {
            const kelas = o.dataset.kelas;
            const pemegang = WALI_ID_PER_KELAS[kelas];
            const terkunci = pemegang !== undefined && !idBatch.has(String(pemegang));
            const olehLain = dipilih[kelas] && dipilih[kelas].id !== k.dataset.id;
            o.disabled = !!(terkunci || olehLain);
            o.textContent = kelas + (terkunci ? ' \u2014 sudah ada wali: ' + WALI_PER_KELAS[kelas]
                                  : olehLain ? ' \u2014 dipilih untuk ' + dipilih[kelas].nama : '');
        });
    });
}

function ubahAkunTerpisah(ids, w) {
    const daftar = document.getElementById('ubahTerpisahDaftar');
    const tpl = document.getElementById('tplUbahAkunTerpisah').innerHTML;
    daftar.innerHTML = '';
    ids.forEach((id, i) => {
        const cek = w.querySelector('.massal-cek[value="' + id + '"]');
        const btn = cek && cek.closest('tr').querySelector('.btn-edit-akun');
        if (!btn) return;
        const d = btn.dataset;
        daftar.insertAdjacentHTML('beforeend', tpl.replaceAll('__ID__', id));
        const k = daftar.lastElementChild;
        k.querySelector('.ubah-no').textContent = '#' + (i + 1);
        k.querySelector('.ubah-username').textContent = d.username;
        k.querySelector('.f-nama').value = d.nama || '';
        k.querySelector('.f-hp').value = d.hp || '';
        k.querySelector('.f-role').value = d.role;
        k.querySelector('.f-wali').value = d.wali || '';
    });
    document.getElementById('ubahTerpisahJumlah').textContent = daftar.children.length;
    segarkanWaliTerpisah();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalUbahAkunTerpisah')).show();
}
['change', 'input'].forEach(ev => document.getElementById('ubahTerpisahDaftar')?.addEventListener(ev, function (e) {
    if (e.target.matches('.f-wali, .f-role') || (ev === 'input' && e.target.matches('.f-nama'))) segarkanWaliTerpisah();
}));

attachSearch('akun-search', 'akun-row');
attachSearch('kelas-search', 'kelas-row');
attachSearch('mapel-search', 'mapel-row');
attachSearch('ruangan-search', 'ruangan-row');
</script>
@endpush
@endsection
