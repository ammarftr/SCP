@extends('auth.shell')

@section('title', 'Masukkan Kode')

@section('form')
                <h3 class="fw-semibold mb-1">Masukkan Kode</h3>
                <p class="text-muted mb-4">Kode 6 digit untuk NIS / NIP <strong>{{ $username }}</strong></p>

                @if(session('status'))
                    <div class="alert alert-info py-2 small">{{ session('status') }}</div>
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

                <form action="{{ route('password.kode.verify') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kode dari email</label>
                        <input type="text" name="kode" class="form-control text-center fs-4" style="letter-spacing:.45rem;"
                               inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                               placeholder="000000" required autofocus>
                        <div class="form-text">Berlaku 10 menit.</div>
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100 mb-3">
                        Verifikasi Kode
                    </button>
                </form>

                <form action="{{ route('password.kode.resend') }}" method="POST" class="text-center mb-2">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0">Kirim ulang kode</button>
                </form>
                <div class="text-center">
                    <a href="{{ route('password.forgot') }}" class="small text-decoration-none">Ganti NIS / NIP</a>
                </div>
@endsection
