@extends('auth.shell')

@section('title', 'Lupa Kata Sandi')

@section('form')
                <h3 class="fw-semibold mb-1">Lupa Kata Sandi</h3>
                <p class="text-muted mb-4">Masukkan NIS / NIP. Kode verifikasi dikirim ke email pemulihan akun Anda.</p>

                @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('password.forgot.verify') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">NIS / NIP</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan NIS / NIP" required maxlength="50" autofocus value="{{ old('username') }}">
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100 mb-3">
                        Kirim Kode
                    </button>
                    <p class="small text-muted mb-3">Belum punya email pemulihan? Minta Waka Kurikulum mereset kata sandi Anda.</p>
                    <div class="text-center">
                        <a href="{{ route('login') }}" class="small text-decoration-none">Kembali ke login</a>
                    </div>
                </form>
@endsection
