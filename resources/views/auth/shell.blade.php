<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - Kemajuan Kelas Sekolah</title>
    <link rel="icon" href="{{ asset('images/logo-sekolah.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    @include('partials.pwa-head')
</head>
<body>
    <div class="scp-wrap">
        <div class="scp-side">
            <div class="bg-photo"></div>
            <div class="bg-overlay"></div>
            <div>
                <div class="brand-row">
                    <div class="brand-icon"><img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo Sekolah"></div>
                    <div>
                        <div class="fw-semibold font-display">SMK Negeri 1 Bantul</div>
                        <div class="text-white-50 small">Presensi dan Kemajuan Sekolah</div>
                    </div>
                </div>
                <h2>Sistem Presensi &amp; Jurnal Sekolah</h2>

                <p class="text-white-50 mt-3 mb-0">Catat jurnal mengajar, presensi siswa, dan perizinan di satu tempat.</p>
            </div>
            <p class="text-white-50 small mb-0">&copy; {{ date('Y') }} SMK Negeri 1 Bantul</p>
        </div>

        <div class="scp-form-col">
            <div class="scp-form-card">
                <div class="mobile-brand">
                    <div class="brand-icon"><img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo Sekolah"></div>
                    <div class="fw-semibold font-display">SMK Negeri 1 Bantul</div>
                </div>

@yield('form')
            </div>
        </div>
    </div>
<script src="{{ asset('js/password-ui.js') }}"></script>
@include('partials.pwa-script')
</body>
</html>
