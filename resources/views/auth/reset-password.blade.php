@extends('auth.shell')

@section('title', 'Kata Sandi Baru')

@section('form')
                <h3 class="fw-semibold mb-1">Buat Kata Sandi Baru</h3>
                <p class="text-muted mb-4">Akun: <strong>{{ $username }}</strong></p>

                @if($errors->any())
                    <div class="alert alert-danger py-2 small">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('password.reset.save') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kata Sandi Baru</label>
                        <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Ulangi Kata Sandi Baru</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" placeholder="Ketik ulang kata sandi baru" required>
                    </div>
                    <button type="submit" class="btn btn-scp-primary w-100 mb-3">
                        Simpan Kata Sandi Baru
                    </button>
                    <div class="text-center">
                        <a href="{{ route('login') }}" class="small text-decoration-none">Batal</a>
                    </div>
                </form>
@endsection
