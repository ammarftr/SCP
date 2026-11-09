<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Presensi &amp; Jurnal Sekolah') - SCP</title>
    <link rel="icon" href="{{ asset('images/logo-sekolah.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @include('partials.pwa-head')
    @stack('styles')
    <style>
        .scp-toasts {
            position: fixed;
            top: calc(env(safe-area-inset-top, 0px) + 14px);
            right: 16px;
            width: min(380px, calc(100vw - 32px));
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .scp-toast {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 12px 14px 14px;
            background: #fff;
            color: #1f2937;
            border-radius: 12px;
            border-left: 5px solid var(--warna);
            box-shadow: 0 10px 30px rgba(15, 23, 42, .18), 0 2px 6px rgba(15, 23, 42, .08);
            overflow: hidden;
            pointer-events: auto;
            font-size: .92rem;
            line-height: 1.4;
            animation: scp-toast-masuk .32s cubic-bezier(.2, .8, .2, 1);
        }
        .scp-toast-sukses { --warna: #1F8A5C; }
        .scp-toast-gagal  { --warna: #dc2626; }
        .scp-toast-ikon { color: var(--warna); font-size: 1.15rem; margin-top: 1px; }
        .scp-toast-isi { flex: 1; min-width: 0; word-break: break-word; }
        .scp-toast-isi > div + div { margin-top: 4px; }
        .scp-toast-x {
            border: 0; background: transparent; color: #6b7280;
            padding: 2px 4px; line-height: 1; cursor: pointer; border-radius: 6px;
        }
        .scp-toast-x:hover { background: #f3f4f6; color: #111827; }
        .scp-toast-garis {
            position: absolute; left: 0; right: 0; bottom: 0; height: 3px;
            background: var(--warna); opacity: .55;
            transform-origin: left center;
            animation: scp-toast-garis var(--lama, 4s) linear forwards;
        }
        .scp-toast:hover .scp-toast-garis,
        .scp-toast.tahan .scp-toast-garis { animation-play-state: paused; }
        .scp-toast.keluar { animation: scp-toast-keluar .25s ease-in forwards; }

        @keyframes scp-toast-masuk  { from { opacity: 0; transform: translateX(24px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes scp-toast-keluar { to   { opacity: 0; transform: translateX(24px); } }
        @keyframes scp-toast-garis  { from { transform: scaleX(1); } to { transform: scaleX(0); } }

        @media (max-width: 575.98px) {
            .scp-toasts { right: 12px; left: 12px; width: auto; }
            .scp-toast { animation-name: scp-toast-masuk-hp; }
            .scp-toast.keluar { animation-name: scp-toast-keluar-hp; }
            @keyframes scp-toast-masuk-hp  { from { opacity: 0; transform: translateY(-16px); } to { opacity: 1; transform: none; } }
            @keyframes scp-toast-keluar-hp { to   { opacity: 0; transform: translateY(-16px); } }
        }
        @media (prefers-reduced-motion: reduce) {
            .scp-toast, .scp-toast.keluar { animation-duration: .01s; }
        }
    </style>
</head>
<body>

    @php
        $currentRole = session('id_role');
        $roleLabels = [4 => 'Waka Kurikulum', 3 => 'Wali Kelas', 2 => 'Guru', 7 => 'Guru BK', 5 => 'Siswa'];
        $roleLabel = $roleLabels[$currentRole] ?? 'Pengguna';
        $kelasWaliLabel = null;
        if (in_array((int) $currentRole, [2, 3, 7], true)) {
            $kelasWaliLabel = \Illuminate\Support\Facades\DB::table('users')->where('id_user', session('id_user'))->value('id_kelas_wali');
        }
        if ((int) $currentRole === 3) {
            $roleLabel = 'Wali Kelas ' . ($kelasWaliLabel ?: '(belum ditetapkan)');
        } elseif ((int) $currentRole === 2 && $kelasWaliLabel) {
            $roleLabel = 'Guru & Wali Kelas ' . $kelasWaliLabel;
        } elseif ((int) $currentRole === 7 && $kelasWaliLabel) {
            $roleLabel = 'Guru BK & Wali Kelas ' . $kelasWaliLabel;
        }
        $userName = session('nama_lengkap', 'Pengguna');
        $initials = collect(explode(' ', trim($userName)))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('');
        // Foto profil: dibaca sekali per sesi lalu disimpan di session ('' = tidak punya foto)
        if (!session()->has('foto_profil')) {
            session(['foto_profil' => (string) \Illuminate\Support\Facades\DB::table('users')->where('id_user', session('id_user'))->value('foto_profil')]);
        }
        $fotoProfil = session('foto_profil');
    @endphp

    <div class="scp-backdrop" id="scpBackdrop"></div>

    <aside class="scp-sidebar" id="scpSidebar">
        <div class="brand">
            <div class="brand-icon"><img src="{{ asset('images/logo-sekolah.png') }}" alt="Logo Sekolah"></div>
            <div class="brand-text">
                <div class="name">SCP - Presensi &amp; Jurnal Sekolah</div>
                <small>SMK Negeri 1 Bantul</small>
            </div>
        </div>

        <div class="role-chip">
            <span class="dot"></span>
            <span>Masuk sebagai <strong style="color:#fff;">{{ $roleLabel }}</strong></span>
        </div>

        <nav>
            @if($currentRole == 4)
                <div class="nav-section-title">Waka Kurikulum</div>
                <a href="{{ route('waka.dashboard') }}" class="nav-link {{ request()->routeIs('waka.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i> Beranda
                </a>
                <a href="{{ route('akun.index') }}" class="nav-link {{ request()->routeIs('akun.index') ? 'active' : '' }}">
                    <i class="bi bi-database"></i> Manajemen Data
                </a>
                <a href="{{ route('waka.jadwal') }}" class="nav-link {{ request()->routeIs('waka.jadwal') ? 'active' : '' }}">
                    <i class="bi bi-calendar3"></i> Manajemen Jadwal
                </a>
                <a href="{{ route('waka.jurnal.rekap') }}" class="nav-link {{ request()->routeIs('waka.jurnal.rekap') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> Rekap Jurnal Guru
                </a>
                <a href="{{ route('waka.impor.index') }}" class="nav-link {{ request()->routeIs('waka.impor.*') ? 'active' : '' }}">
                    <i class="bi bi-person-plus"></i> Impor Siswa
                </a>
                <a href="{{ route('waka.kenaikan.index') }}" class="nav-link {{ request()->routeIs('waka.kenaikan.*') ? 'active' : '' }}">
                    <i class="bi bi-mortarboard"></i> Kenaikan Kelas
                </a>
                <div class="nav-section-title">Mengajar</div>
                <a href="{{ route('guru.dashboard') }}" class="nav-link {{ request()->routeIs('guru.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-person-workspace"></i> Beranda Guru
                </a>
                <a href="{{ route('jadwal.index') }}" class="nav-link {{ request()->routeIs('jadwal.index') ? 'active' : '' }}">
                    <i class="bi bi-calendar-week"></i> Jadwal Pelajaran
                </a>
                <a href="{{ route('jurnal.index') }}" class="nav-link {{ request()->routeIs('jurnal.index') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> Jurnal Mengajar
                </a>
            @elseif($currentRole == 2 || $currentRole == 7)
                <div class="nav-section-title">{{ $currentRole == 7 ? 'Guru BK' : 'Guru' }}</div>
                <a href="{{ route('guru.dashboard') }}" class="nav-link {{ request()->routeIs('guru.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i> Beranda
                </a>
                <a href="{{ route('jadwal.index') }}" class="nav-link {{ request()->routeIs('jadwal.index') ? 'active' : '' }}">
                    <i class="bi bi-calendar-week"></i> Jadwal Pelajaran
                </a>
                <a href="{{ route('jurnal.index') }}" class="nav-link {{ request()->routeIs('jurnal.index') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> Jurnal Mengajar
                </a>
                @if($kelasWaliLabel || $currentRole == 7)
                    <div class="nav-section-title">{{ $currentRole == 7 ? 'Kesiswaan (Kelas yang Diampu)' : 'Wali Kelas ' . $kelasWaliLabel }}</div>
                    <a href="{{ route('kesiswaan.izin.index') }}" class="nav-link {{ request()->routeIs('kesiswaan.izin.index') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-check"></i> Verifikasi Izin
                    </a>
                    <a href="{{ route('kesiswaan.rekap.absensi') }}" class="nav-link {{ request()->routeIs('kesiswaan.rekap.absensi') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart"></i> Rekap Absensi
                    </a>
                    <a href="{{ route('kesiswaan.laporan.izin') }}" class="nav-link {{ request()->routeIs('kesiswaan.laporan.izin') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-data"></i> Laporan Izin Harian
                    </a>
                @endif
            @elseif($currentRole == 3)
                <div class="nav-section-title">Wali Kelas</div>
                <a href="{{ route('kesiswaan.izin.index') }}" class="nav-link {{ request()->routeIs('kesiswaan.izin.index') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-check"></i> Verifikasi Izin
                </a>
                <a href="{{ route('kesiswaan.rekap.absensi') }}" class="nav-link {{ request()->routeIs('kesiswaan.rekap.absensi') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart"></i> Rekap Absensi
                </a>
                <a href="{{ route('kesiswaan.laporan.izin') }}" class="nav-link {{ request()->routeIs('kesiswaan.laporan.izin') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-data"></i> Laporan Izin Harian
                </a>
            @elseif($currentRole == 5)
                <div class="nav-section-title">Siswa</div>
                <a href="{{ route('siswa.izin.index') }}" class="nav-link {{ request()->routeIs('siswa.izin.index') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-medical"></i> Izin &amp; Kehadiran
                </a>
            @endif

        </nav>

    </aside>

    <div class="scp-main">
        <header class="scp-topbar">
            <button class="burger" id="scpBurger" type="button" aria-label="Buka menu">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="topbar-titles">
                <p class="page-title">@yield('title', 'Beranda')</p>
                @hasSection('subtitle')<p class="page-subtitle">@yield('subtitle')</p>@endif
            </div>

            <div class="dropdown ms-auto">
                <button class="user-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar">
                        @if($fotoProfil)
                            <img src="{{ route('profil.foto', ['v' => md5($fotoProfil)]) }}" alt="Foto profil">
                        @else
                            {{ strtoupper($initials ?: 'U') }}
                        @endif
                    </div>
                    <div class="text-start user-text">
                        <div class="u-name">{{ $userName }}</div>
                        <div class="u-role">{{ $roleLabel }}</div>
                    </div>
                    <i class="bi bi-chevron-down small text-muted ms-1 user-chev"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li class="px-3 py-2 d-sm-none">
                        <div class="fw-semibold small">{{ $userName }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $roleLabel }}</div>
                    </li>
                    <li class="d-sm-none"><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('profil.index') }}"><i class="bi bi-person-circle me-2"></i>Profil Saya</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST" data-confirm="Yakin ingin keluar?">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="scp-content">
            {{-- Notifikasi melayang (toast): tidak mendorong isi halaman ke bawah --}}
            <div class="scp-toasts" aria-live="polite">
                @if(session('success'))
                    <div class="scp-toast scp-toast-sukses" role="status" style="--lama: 4s;">
                        <i class="bi bi-check-circle-fill scp-toast-ikon"></i>
                        <div class="scp-toast-isi">{{ session('success') }}</div>
                        <button type="button" class="scp-toast-x" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                        <span class="scp-toast-garis"></span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="scp-toast scp-toast-gagal" role="alert" style="--lama: 10s;">
                        <i class="bi bi-exclamation-triangle-fill scp-toast-ikon"></i>
                        <div class="scp-toast-isi">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                        <button type="button" class="scp-toast-x" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                        <span class="scp-toast-garis"></span>
                    </div>
                @endif
            </div>

            @yield('content')
        </main>
    </div>

    @php
        // Menu bottom navbar (mobile). Label dipendekkan agar muat; maksimal 5 slot.
        $navItems = [];
        if ($currentRole == 4) {
            $navItems = [
                ['waka.dashboard', 'bi-grid-1x2', 'Beranda'],
                ['akun.index', 'bi-database', 'Data'],
                ['waka.jadwal', 'bi-calendar3', 'Jadwal'],
                ['waka.jurnal.rekap', 'bi-journal-text', 'Rekap'],
                ['guru.dashboard', 'bi-person-workspace', 'Mengajar'],
            ];
        } elseif ($currentRole == 2 || $currentRole == 7) {
            $navItems = [
                ['guru.dashboard', 'bi-grid-1x2', 'Beranda'],
                ['jadwal.index', 'bi-calendar-week', 'Jadwal'],
                ['jurnal.index', 'bi-journal-text', 'Jurnal'],
            ];
            if ($kelasWaliLabel || $currentRole == 7) {
                $navItems[] = ['kesiswaan.izin.index', 'bi-file-earmark-check', 'Izin'];
                $navItems[] = ['kesiswaan.rekap.absensi', 'bi-bar-chart', 'Absensi'];
                $navItems[] = ['kesiswaan.laporan.izin', 'bi-clipboard-data', 'Laporan'];
            }
        } elseif ($currentRole == 3) {
            $navItems = [
                ['kesiswaan.izin.index', 'bi-file-earmark-check', 'Izin'],
                ['kesiswaan.rekap.absensi', 'bi-bar-chart', 'Absensi'],
                ['kesiswaan.laporan.izin', 'bi-clipboard-data', 'Laporan'],
            ];
        } elseif ($currentRole == 5) {
            $navItems = [['siswa.izin.index', 'bi-file-earmark-medical', 'Izin']];
        }
        // Profil melengkapi menu bila item sedikit; bila >5 item, tampilkan 4 + "Lainnya" (buka sidebar).
        if (count($navItems) < 4) {
            $navItems[] = ['profil.index', 'bi-person-circle', 'Profil'];
        }
        $navOverflow = count($navItems) > 5;
        $navVisible = $navOverflow ? array_slice($navItems, 0, 4) : $navItems;
    @endphp
    @if(count($navVisible))
    <nav class="scp-bottomnav" aria-label="Menu utama">
        @foreach($navVisible as [$navRoute, $navIcon, $navLabel])
            <a href="{{ route($navRoute) }}" class="bn-item {{ request()->routeIs($navRoute) ? 'active' : '' }}" @if(request()->routeIs($navRoute)) aria-current="page" @endif>
                <i class="bi {{ $navIcon }}"></i><span>{{ $navLabel }}</span>
            </a>
        @endforeach
        @if($navOverflow)
            <button type="button" class="bn-item" id="scpBottomMore" aria-label="Menu lainnya">
                <i class="bi bi-grid-3x3-gap"></i><span>Lainnya</span>
            </button>
        @endif
    </nav>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast: hilang saat garis waktunya habis, atau saat tombol × ditekan.
        // Timer berhenti saat kursor di atasnya (CSS :hover) atau layar disentuh.
        document.querySelectorAll('.scp-toast').forEach(function (el) {
            function tutup() {
                if (el.classList.contains('keluar')) return;
                el.classList.add('keluar');
                el.addEventListener('animationend', function (e) { if (e.target === el) el.remove(); });
                setTimeout(function () { el.remove(); }, 600);
            }
            el.querySelector('.scp-toast-x').addEventListener('click', tutup);
            el.querySelector('.scp-toast-garis').addEventListener('animationend', tutup);
            el.addEventListener('touchstart', function () { el.classList.add('tahan'); }, { passive: true });
            el.addEventListener('touchend', function () { el.classList.remove('tahan'); });
            el.addEventListener('touchcancel', function () { el.classList.remove('tahan'); });
        });
    </script>
    <script>
        const sidebar = document.getElementById('scpSidebar');
        const backdrop = document.getElementById('scpBackdrop');
        const burger = document.getElementById('scpBurger');
        function openSidebar(){ sidebar.classList.add('show'); backdrop.classList.add('show'); }
        function closeSidebar(){ sidebar.classList.remove('show'); backdrop.classList.remove('show'); }
        burger?.addEventListener('click', openSidebar);
        document.getElementById('scpBottomMore')?.addEventListener('click', openSidebar);
        backdrop?.addEventListener('click', closeSidebar);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="{{ asset('js/select-search.js') }}?v={{ @filemtime(public_path('js/select-search.js')) }}"></script>
    <script src="{{ asset('js/confirm-modal.js') }}?v={{ @filemtime(public_path('js/confirm-modal.js')) }}"></script>
    <script src="{{ asset('js/auto-filter.js') }}?v={{ @filemtime(public_path('js/auto-filter.js')) }}"></script>
    <script src="{{ asset('js/aksi-massal.js') }}?v={{ @filemtime(public_path('js/aksi-massal.js')) }}"></script>
    <script src="{{ asset('js/paginasi.js') }}?v={{ @filemtime(public_path('js/paginasi.js')) }}"></script>
    <script src="{{ asset('js/tabel-responsif.js') }}?v={{ @filemtime(public_path('js/tabel-responsif.js')) }}"></script>
    <script src="{{ asset('js/image-compress.js') }}?v={{ @filemtime(public_path('js/image-compress.js')) }}"></script>
    <script src="{{ asset('js/camera-capture.js') }}?v={{ @filemtime(public_path('js/camera-capture.js')) }}"></script>
    @include('partials.pwa-script')
    <script src="{{ asset('js/password-ui.js') }}?v={{ @filemtime(public_path('js/password-ui.js')) }}"></script>
    @stack('scripts')
</body>
</html>
