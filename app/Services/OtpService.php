<?php

namespace App\Services;

use App\Mail\KodeOtpMail;
use App\Mail\NotifikasiAkunMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pembuatan, pengiriman, dan pemeriksaan kode OTP lewat email.
 *
 * - Kode 6 digit dari random_int (aman secara kriptografis).
 * - Yang disimpan di database hanya HMAC-nya (diikat ke APP_KEY, id_user, dan tujuan).
 * - Berlaku 10 menit, sekali pakai, maksimal 5 kali salah (setelah itu kode terkunci).
 * - Membuat kode baru otomatis membatalkan kode lama untuk tujuan yang sama.
 */
class OtpService
{
    public const TUJUAN_RESET = 'reset';
    public const TUJUAN_EMAIL = 'email';
    public const BERLAKU_MENIT = 10;
    public const MAKS_PERCOBAAN = 5;

    private function hash(int $idUser, string $tujuan, string $kode): string
    {
        return hash_hmac('sha256', $idUser . '|' . $tujuan . '|' . $kode, (string) config('app.key'));
    }

    /** Buat kode baru dan kembalikan kode polos (untuk dikirim lewat email, jangan disimpan/dicatat). */
    public function buat(int $idUser, string $tujuan, string $email): string
    {
        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($idUser, $tujuan, $email, $kode) {
            DB::table('kode_otp')
                ->where('id_user', $idUser)
                ->where('tujuan', $tujuan)
                ->whereNull('dipakai_pada')
                ->update(['dipakai_pada' => now()]);

            DB::table('kode_otp')->insert([
                'id_user'          => $idUser,
                'tujuan'           => $tujuan,
                'email'            => $email,
                'kode_hash'        => $this->hash($idUser, $tujuan, $kode),
                'kedaluwarsa_pada' => now()->addMinutes(self::BERLAKU_MENIT),
                'percobaan'        => 0,
                'created_at'       => now(),
            ]);
        });

        return $kode;
    }

    /**
     * Periksa kode yang dimasukkan pengguna.
     *
     * @return array{0: string, 1: ?object} [status, baris]
     *         status: 'ok' | 'salah' | 'kedaluwarsa' | 'terkunci'
     *         (baris hanya terisi saat 'ok')
     */
    public function periksa(int $idUser, string $tujuan, string $kode): array
    {
        return DB::transaction(function () use ($idUser, $tujuan, $kode) {
            // lockForUpdate: dua tebakan serentak tidak bisa melewati batas percobaan
            $baris = DB::table('kode_otp')
                ->where('id_user', $idUser)
                ->where('tujuan', $tujuan)
                ->whereNull('dipakai_pada')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$baris || Carbon::parse($baris->kedaluwarsa_pada)->isPast()) {
                return ['kedaluwarsa', null];
            }

            if ((int) $baris->percobaan >= self::MAKS_PERCOBAAN) {
                return ['terkunci', null];
            }

            // Percobaan dihitung SEBELUM dibandingkan, jadi tebakan benar pun tetap tercatat
            DB::table('kode_otp')->where('id', $baris->id)->increment('percobaan');

            if (hash_equals($baris->kode_hash, $this->hash($idUser, $tujuan, $kode))) {
                DB::table('kode_otp')->where('id', $baris->id)->update(['dipakai_pada' => now()]);

                return ['ok', $baris];
            }

            return ['salah', null];
        });
    }

    /** Kode aktif (belum dipakai, belum kedaluwarsa, belum terkunci) milik pengguna, atau null. */
    public function aktif(int $idUser, string $tujuan): ?object
    {
        $baris = DB::table('kode_otp')
            ->where('id_user', $idUser)
            ->where('tujuan', $tujuan)
            ->whereNull('dipakai_pada')
            ->orderByDesc('id')
            ->first();

        if (!$baris || Carbon::parse($baris->kedaluwarsa_pada)->isPast()
            || (int) $baris->percobaan >= self::MAKS_PERCOBAAN) {
            return null;
        }

        return $baris;
    }

    // ------------------------------------------------------------------
    // Pengiriman email. Semuanya dijalankan SETELAH respons dikirim ke browser,
    // supaya halaman tidak menunggu SMTP dan waktu respons sama untuk semua NIS/NIP
    // (penyerang tidak bisa membedakan akun yang ada dari waktu responsnya).
    // ------------------------------------------------------------------

    /**
     * Lupa kata sandi: cari akun, buat kode, kirim ke email terverifikasi.
     * Kalau akun tidak ada / nonaktif / belum punya email terverifikasi, tidak terjadi apa-apa.
     * Dipanggil dari dalam dispatch(...)->afterResponse() di controller.
     */
    public function kirimKodeReset(string $username): void
    {
        try {
            $user = DB::table('users')->where('username', $username)->first();

            if (!$user || (int) $user->is_aktif !== 1 || empty($user->email) || empty($user->email_verified_at)) {
                return;
            }

            $kode = $this->buat((int) $user->id_user, self::TUJUAN_RESET, $user->email);

            Mail::to($user->email)->send(
                new KodeOtpMail($user->nama_lengkap, $kode, 'Atur ulang kata sandi', self::BERLAKU_MENIT)
            );
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim kode atur ulang kata sandi: ' . $e->getMessage());
        }
    }

    /** Verifikasi email baru (dari halaman Profil). Kode sudah dibuat oleh pemanggil. */
    public function kirimKodeEmail(string $email, string $nama, string $kode): void
    {
        dispatch(function () use ($email, $nama, $kode) {
            try {
                Mail::to($email)->send(
                    new KodeOtpMail($nama, $kode, 'Verifikasi email pemulihan', self::BERLAKU_MENIT)
                );
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim kode verifikasi email: ' . $e->getMessage());
            }
        })->afterResponse();
    }

    /** Pemberitahuan keamanan (kata sandi diubah, email pemulihan diganti, dll). */
    public function kirimNotifikasi(string $email, string $nama, string $judul, array $baris): void
    {
        dispatch(function () use ($email, $nama, $judul, $baris) {
            try {
                Mail::to($email)->send(new NotifikasiAkunMail($nama, $judul, $baris));
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim pemberitahuan akun: ' . $e->getMessage());
            }
        })->afterResponse();
    }
}
