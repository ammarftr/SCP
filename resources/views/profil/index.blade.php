@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
@php
    $inisialProfil = collect(explode(' ', trim($user->nama_lengkap)))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp
<div class="row g-3">
    <div class="col-12">
        <div class="scp-card">
            <div class="scp-card-header accent-primary">Foto Profil</div>
            <div class="scp-card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="foto-profil-besar" id="fotoProfilPratinjau">
                        @if($user->foto_profil)
                            <img src="{{ route('profil.foto', ['v' => md5($user->foto_profil)]) }}" alt="Foto profil">
                        @else
                            {{ strtoupper($inisialProfil ?: 'U') }}
                        @endif
                    </div>
                    <div class="flex-grow-1" style="min-width:240px;">
                        <form action="{{ route('profil.foto.update') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-start">
                            @csrf
                            <div class="flex-grow-1" style="min-width:200px;">
                                <input type="file" name="foto" id="fotoProfilInput" class="form-control @error('foto') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" data-compress required>
                                @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">JPG, PNG, atau WEBP, maks. 5 MB. Foto dipotong persegi di bagian tengah.</div>
                            </div>
                            <button type="submit" class="btn btn-scp-primary">Simpan Foto</button>
                        </form>
                        @if($user->foto_profil)
                            <form action="{{ route('profil.foto.hapus') }}" method="POST" class="mt-2" data-confirm="Hapus foto profil?">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Hapus Foto</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var input = document.getElementById('fotoProfilInput');
        var box = document.getElementById('fotoProfilPratinjau');
        if (!input || !box) return;
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f || !/^image\//.test(f.type)) return;
            var img = document.createElement('img');
            img.alt = 'Pratinjau foto profil';
            img.src = URL.createObjectURL(f);
            box.innerHTML = '';
            box.appendChild(img);
        });
    })();
    </script>

    <div class="col-lg-6">
        <div class="scp-card">
            <div class="scp-card-header accent-primary">Informasi Akun</div>
            <div class="scp-card-body">
                <form action="{{ route('profil.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" class="form-control bg-light" value="{{ $user->nama_lengkap }}" disabled readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">NIS / NIP</label>
                        <input type="text" class="form-control bg-light" value="{{ $user->username }}" disabled readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No. HP</label>
                        <input type="text" name="no_hp" class="form-control" value="{{ old('no_hp', $user->no_hp) }}">
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="scp-card">
            <div class="scp-card-header">Ubah Kata Sandi</div>
            <div class="scp-card-body">
                <form action="{{ route('profil.password') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi Lama</label>
                        <input type="password" name="password_lama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi Baru</label>
                        <input type="password" name="password_baru" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" name="password_baru_confirmation" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-scp-accent w-100">Ubah Kata Sandi</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="scp-card">
            <div class="scp-card-header">Email Pemulihan</div>
            <div class="scp-card-body">
                @if($user->email && $user->email_verified_at)
                    <div class="alert alert-success py-2 small">
                        <i class="bi bi-check-circle me-1"></i> Terverifikasi: <strong>{{ $user->email }}</strong>
                    </div>
                @else
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Belum ada email pemulihan. Tanpa email ini Anda tidak bisa mengatur ulang kata sandi sendiri kalau lupa.
                    </div>
                @endif

                <form action="{{ route('profil.email.kirim') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">{{ $user->email ? 'Email baru' : 'Alamat email' }}</label>
                        <input type="email" name="email" class="form-control" maxlength="150" placeholder="nama@gmail.com" required value="{{ old('email') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi Anda</label>
                        <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100">Kirim Kode Verifikasi</button>
                </form>

                @if($emailTertunda)
                    <hr>
                    <form action="{{ route('profil.email.verifikasi') }}" method="POST" autocomplete="off">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Kode yang dikirim ke {{ $emailTertunda->email }}</label>
                            <input type="text" name="kode" class="form-control text-center fs-5" style="letter-spacing:.35rem;"
                                   inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                            <div class="form-text">Berlaku 10 menit.</div>
                        </div>
                        <button type="submit" class="btn btn-scp-accent w-100">Verifikasi Email</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
