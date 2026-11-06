<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Kemajuan Kelas Sekolah') - SCP</title>
    <link rel="icon" href="{{ asset('images/logo-sekolah.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @include('partials.pwa-head')
    @stack('styles')
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
                <div class="name">Kemajuan Kelas Sekolah</div>
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
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <ul class="mb-0 d-inline">
                        @foreach($errors->all() as $error)
                            <li class="d-inline list-unstyled">{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            @endif

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
    <script src="{{ asset('js/image-compress.js') }}?v={{ @filemtime(public_path('js/image-compress.js')) }}"></script>
    <script src="{{ asset('js/camera-capture.js') }}?v={{ @filemtime(public_path('js/camera-capture.js')) }}"></script>
    @include('partials.pwa-script')
    <script src="{{ asset('js/password-ui.js') }}?v={{ @filemtime(public_path('js/password-ui.js')) }}"></script>
    @stack('scripts')
</body>
</html>
