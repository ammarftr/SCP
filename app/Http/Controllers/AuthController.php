<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Pesan yang SAMA ditampilkan untuk NIS/NIP yang ada maupun tidak, supaya akun tidak bisa ditebak. */
    private const PESAN_KODE_TERKIRIM = 'Jika NIS / NIP terdaftar dan memiliki email pemulihan yang sudah diverifikasi, kode 6 digit sudah dikirim ke email tersebut. Cek juga folder spam.';

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required|min:8',
        ], [
            'username.required' => 'Nama pengguna wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min'      => 'Kata sandi minimal 8 karakter.',
        ]);

        // Batasi percobaan masuk: maksimal 5 kali per 15 menit untuk kombinasi nama pengguna + alamat IP
        $kunci = 'masuk:' . strtolower((string) $request->username) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $menit = max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60));
            return back()
                ->withErrors(['username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$menit} menit."])
                ->withInput($request->only('username'));
        }

        $user = DB::table('users')->where('username', $request->username)->first();

        if ($user && (int) $user->is_aktif === 1 && Hash::check($request->password, $user->password_hash)) {
            RateLimiter::clear($kunci);
            $request->session()->regenerate();
            session([
                'id_user' => $user->id_user,
                'username' => $user->username,
                'nama_lengkap' => $user->nama_lengkap,
                'id_role' => $user->id_role,
                // Dipakai PastikanPeran: sesi lama dikeluarkan bila kata sandi diganti sesudah waktu ini
                'login_at' => time(),
            ]);

            // REDIRECT BERDASARKAN ID_ROLE (5 role yang dipakai sistem):
            // 4 = Waka Kurikulum
            // 3 = Wali Kelas -> verifikasi izin (Tim Kesiswaan)
            // 7 = Guru BK -> beranda guru (jadwal & jurnal mengajar) + menu Tim Kesiswaan (semua kelas)
            // 2 = Guru Mapel -> jadwal & jurnal mengajar
            // 5 = Siswa
            switch ($user->id_role) {
                case 4:
                    return redirect()->route('waka.dashboard')->with('success', 'Berhasil masuk.');
                case 3:
                    return redirect()->route('kesiswaan.izin.index')->with('success', 'Berhasil masuk.');
                case 7:
                    return redirect()->route('guru.dashboard')->with('success', 'Berhasil masuk.');
                case 2:
                    return redirect()->route('guru.dashboard')->with('success', 'Berhasil masuk.');
                case 5:
                    return redirect()->route('siswa.izin.index')->with('success', 'Berhasil masuk.');
                default:
                    return redirect()->route('login')->withErrors(['username' => 'Role pengguna tidak dikenali sistem.']);
            }
        }

        RateLimiter::hit($kunci, 900);

        return back()->withErrors(['username' => 'Nama pengguna atau kata sandi salah.']);
    }

    // ==========================================
    // LUPA PASSWORD dengan kode OTP lewat email (semua role)
    //
    // Alur: (1) isi NIS/NIP -> (2) masukkan kode 6 digit dari email -> (3) buat kata sandi baru
    // Syarat: akun sudah punya email pemulihan TERVERIFIKASI (diatur di menu Profil).
    // Akun tanpa email pemulihan: minta Waka Kurikulum mereset kata sandi di Manajemen Akun.
    // ==========================================

    private function otp(): OtpService
    {
        return app(OtpService::class);
    }

    /**
     * Terapkan pembatasan, lalu jadwalkan pengiriman kode SETELAH respons terkirim.
     * Mengembalikan pesan galat bila ditolak, atau null bila diterima.
     * Seluruh pembatasan tidak bergantung pada ada/tidaknya akun, jadi tidak membocorkan akun.
     */
    private function mintaKode(Request $request, string $username): ?string
    {
        $nama = Str::lower($username);
        $kunciIp     = 'lupa-ip:' . $request->ip();
        $kunciUser   = 'lupa-user:' . $nama;
        $kunciJeda   = 'lupa-jeda:' . $nama;
        $kunciGlobal = 'lupa-global';

        if (RateLimiter::tooManyAttempts($kunciGlobal, 300)) {
            return 'Layanan kode sedang sibuk. Coba lagi nanti atau hubungi Waka Kurikulum.';
        }

        foreach ([[$kunciIp, 30], [$kunciUser, 5]] as [$kunci, $maks]) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                $menit = max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60));
                return "Terlalu banyak permintaan kode. Coba lagi dalam {$menit} menit.";
            }
        }

        if (RateLimiter::tooManyAttempts($kunciJeda, 1)) {
            return 'Tunggu ' . RateLimiter::availableIn($kunciJeda) . ' detik sebelum meminta kode lagi.';
        }

        RateLimiter::hit($kunciIp, 3600);
        RateLimiter::hit($kunciUser, 3600);
        RateLimiter::hit($kunciJeda, 60);
        RateLimiter::hit($kunciGlobal, 86400);

        // Pencarian akun + pembuatan kode + pengiriman email, semuanya setelah respons terkirim
        dispatch(fn () => $this->otp()->kirimKodeReset($username))->afterResponse();

        return null;
    }

    public function showForgotForm()
    {
        return view('auth.lupa-password');
    }

    /** Langkah 1: terima NIS/NIP, kirim kode ke email pemulihan. */
    public function kirimKode(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:50',
        ], [
            'username.required' => 'NIS / NIP wajib diisi.',
        ]);

        $username = trim($request->username);

        if ($pesan = $this->mintaKode($request, $username)) {
            return back()->withInput($request->only('username'))->withErrors(['username' => $pesan]);
        }

        session(['reset_username' => $username]);

        return redirect()->route('password.kode')->with('status', self::PESAN_KODE_TERKIRIM);
    }

    public function showKodeForm()
    {
        if (!session()->has('reset_username')) {
            return redirect()->route('password.forgot');
        }

        return view('auth.lupa-kode', ['username' => session('reset_username')]);
    }

    public function kirimUlang(Request $request)
    {
        $username = session('reset_username');
        if (!$username) {
            return redirect()->route('password.forgot');
        }

        if ($pesan = $this->mintaKode($request, $username)) {
            return back()->withErrors(['kode' => $pesan]);
        }

        return back()->with('status', self::PESAN_KODE_TERKIRIM);
    }

    /** Langkah 2: periksa kode 6 digit. */
    public function verifyKode(Request $request)
    {
        $username = session('reset_username');
        if (!$username) {
            return redirect()->route('password.forgot')
                ->withErrors(['username' => 'Sesi habis. Masukkan NIS / NIP Anda lagi.']);
        }

        $request->validate([
            'kode' => 'required|string|max:12',
        ], [
            'kode.required' => 'Kode wajib diisi.',
        ]);

        // Pembatas tambahan per IP (jaring pengaman; batas utama ada di OtpService: 5 salah per kode)
        $kunciIp = 'kode-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($kunciIp, 60)) {
            $menit = max(1, (int) ceil(RateLimiter::availableIn($kunciIp) / 60));
            return back()->withErrors(['kode' => "Terlalu banyak percobaan. Coba lagi dalam {$menit} menit."]);
        }
        RateLimiter::hit($kunciIp, 900);

        $kode = preg_replace('/\D+/', '', (string) $request->kode);
        $user = DB::table('users')->where('username', $username)->first();

        $status = 'kedaluwarsa';
        if ($user && (int) $user->is_aktif === 1 && strlen($kode) === 6) {
            [$status] = $this->otp()->periksa((int) $user->id_user, OtpService::TUJUAN_RESET, $kode);
        }

        if ($status !== 'ok') {
            // Satu pesan untuk semua kegagalan (salah / kedaluwarsa / terkunci / akun tidak ada)
            return back()->withErrors([
                'kode' => 'Kode salah, sudah kedaluwarsa, atau terkunci karena terlalu banyak percobaan. Minta kode baru bila perlu.',
            ]);
        }

        $request->session()->regenerate();
        session()->forget('reset_username');
        session([
            'reset_user_id' => $user->id_user,
            'reset_expires' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('password.reset');
    }

    private function resetSessionValid(): bool
    {
        return session()->has('reset_user_id') && session('reset_expires', 0) >= now()->timestamp;
    }

    /** Langkah 3: form kata sandi baru. */
    public function showResetForm()
    {
        if (!$this->resetSessionValid()) {
            session()->forget(['reset_user_id', 'reset_expires']);
            return redirect()->route('password.forgot')
                ->withErrors(['username' => 'Sesi atur ulang kata sandi sudah habis. Silakan minta kode lagi.']);
        }

        $user = DB::table('users')->where('id_user', session('reset_user_id'))->first();
        return view('auth.reset-password', ['username' => $user->username ?? '']);
    }

    public function resetPassword(Request $request)
    {
        if (!$this->resetSessionValid()) {
            session()->forget(['reset_user_id', 'reset_expires']);
            return redirect()->route('password.forgot')
                ->withErrors(['username' => 'Sesi atur ulang kata sandi sudah habis. Silakan minta kode lagi.']);
        }

        $user = DB::table('users')->where('id_user', session('reset_user_id'))->first();
        if (!$user || (int) $user->is_aktif !== 1) {
            session()->forget(['reset_user_id', 'reset_expires']);
            return redirect()->route('password.forgot')
                ->withErrors(['username' => 'Akun tidak ditemukan atau tidak aktif.']);
        }

        $request->validate([
            'password' => 'required|string|min:8|max:72|confirmed',
        ], [
            'password.min'       => 'Kata sandi terlalu pendek! Minimal 8 karakter.',
            'password.max'       => 'Kata sandi terlalu panjang! Maksimal 72 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        if (Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['password' => 'Kata sandi baru tidak boleh sama dengan kata sandi lama.']);
        }
        if (Str::lower($request->password) === Str::lower($user->username)) {
            return back()->withErrors(['password' => 'Kata sandi tidak boleh sama dengan NIS / NIP.']);
        }

        DB::table('users')->where('id_user', $user->id_user)->update([
            'password_hash'       => Hash::make($request->password),
            'password_changed_at' => now(),   // sesi lama akun ini otomatis dikeluarkan
        ]);

        // Batalkan semua kode OTP yang masih menggantung
        DB::table('kode_otp')->where('id_user', $user->id_user)->whereNull('dipakai_pada')->update(['dipakai_pada' => now()]);

        session()->forget(['reset_user_id', 'reset_expires']);

        // Beri tahu pemilik akun lewat email (supaya ketahuan kalau bukan dia yang melakukannya)
        if (!empty($user->email) && !empty($user->email_verified_at)) {
            $this->otp()->kirimNotifikasi($user->email, $user->nama_lengkap, 'Kata sandi akun diubah', [
                'Kata sandi akun SCP Anda (NIS / NIP: ' . $user->username . ') baru saja diatur ulang lewat fitur Lupa Kata Sandi pada ' . now()->format('d-m-Y H:i') . ' (alamat IP: ' . $request->ip() . ').',
                'Kalau ini bukan Anda, segera hubungi Waka Kurikulum.',
            ]);
        }

        return redirect()->route('login')->with('success', 'Kata sandi diganti. Silakan masuk dengan kata sandi baru.');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Berhasil keluar.');
    }
}
