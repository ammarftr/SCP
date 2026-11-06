@extends('auth.shell')

@section('title', 'Masuk')

@section('form')
                <h3 class="fw-semibold mb-1">Selamat Datang</h3>
                <p class="text-muted mb-4">Masuk ke akun Anda</p>

                @if(session('success'))
                    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('login.post') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">NIS / NIP</label>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan NIS / NIP" required value="{{ old('username') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi</label>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100 mb-3">
                        Masuk
                    </button>
                    <div class="text-center">
                        <a href="{{ route('password.forgot') }}" class="small text-decoration-none">Lupa kata sandi?</a>
                    </div>
                </form>
@endsection
