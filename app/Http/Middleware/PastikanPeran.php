<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan pengguna sudah masuk dan (jika ditentukan) memiliki peran yang sesuai.
 *
 * Pemakaian di rute:
 *   ->middleware('peran')       hanya wajib masuk
 *   ->middleware('peran:4')     khusus Waka Kurikulum
 *   ->middleware('peran:3,7')   Wali Kelas atau Guru BK (Guru BK juga mengajar: dashboard, jadwal, jurnal)
 *
 * Peran: 2 = Guru, 3 = Wali Kelas, 4 = Waka Kurikulum, 5 = Siswa, 7 = Guru BK.
 */
class PastikanPeran
{
    /** Halaman awal tiap peran. */
    private const BERANDA = [
        4 => 'waka.dashboard',
        3 => 'kesiswaan.izin.index',
        7 => 'guru.dashboard',
        2 => 'guru.dashboard',
        5 => 'siswa.izin.index',
    ];

    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $sesi = $request->session();

        if (!$sesi->has('id_user')) {
            return redirect()->route('login')
                ->withErrors(['error' => 'Silakan masuk dulu.']);
        }

        // Sesi yang dibuat SEBELUM kata sandi terakhir diganti tidak berlaku lagi
        // (mis. setelah akun yang dibajak mengatur ulang kata sandi lewat Lupa Kata Sandi).
        // Sesi lama tanpa 'login_at' (dibuat sebelum fitur ini) dibiarkan sampai mereka masuk ulang.
        $masukPada = (int) $sesi->get('login_at', 0);
        if ($masukPada > 0) {
            $diubahPada = DB::table('users')->where('id_user', $sesi->get('id_user'))->value('password_changed_at');
            if ($diubahPada && Carbon::parse($diubahPada)->timestamp > $masukPada) {
                $sesi->invalidate();

                return redirect()->route('login')
                    ->withErrors(['error' => 'Kata sandi akun ini baru diubah. Silakan masuk lagi.']);
            }
        }

        $perluPeran = array_map('intval', $peran);
        $peranSaya = (int) $sesi->get('id_role');

        // Guru (2) yang ditetapkan sebagai wali kelas dianggap boleh membuka rute Wali Kelas (3).
        // Datanya tetap dibatasi ke kelas perwaliannya di controller.
        $merangkapWali = $peranSaya === 2
            && in_array(3, $perluPeran, true)
            && DB::table('users')->where('id_user', $sesi->get('id_user'))->whereNotNull('id_kelas_wali')->exists();

        if ($perluPeran && !$merangkapWali && !in_array($peranSaya, $perluPeran, true)) {
            $tujuan = self::BERANDA[$peranSaya] ?? null;

            return redirect()->route($tujuan ?? 'login')
                ->withErrors(['error' => 'Anda tidak punya akses ke halaman itu.']);
        }

        return $next($request);
    }
}
