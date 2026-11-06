<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    private function otp(): OtpService
    {
        return app(OtpService::class);
    }

    public function index()
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Silakan masuk dulu.']);
        }

        $user = DB::table('users')->where('id_user', session('id_user'))->first();

        // Kode verifikasi email yang masih berlaku (kalau ada), supaya kolom kode muncul
        $emailTertunda = $this->otp()->aktif((int) session('id_user'), OtpService::TUJUAN_EMAIL);

        return view('profil.index', compact('user', 'emailTertunda'));
    }

    public function update(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk lagi.']);
        }

        $userId = session('id_user');

        $request->validate([
            'no_hp' => 'nullable|string|max:20',
        ]);

        // NIS / NIP SENGAJA tidak bisa diubah sendiri oleh pengguna karena dipakai
        // sebagai penghubung ke jadwal, jurnal, presensi, dll. Nama Lengkap juga
        // hanya bisa diperbaiki lewat Waka Kurikulum di Manajemen Data Master.
        DB::table('users')->where('id_user', $userId)->update([
            'no_hp' => $request->no_hp,
        ]);

        return redirect()->route('profil.index')->with('success', 'Profil diperbarui.');
    }

    // ==========================================
    // FOTO PROFIL
    // Disimpan privat di storage (bukan folder publik), dipotong persegi 400x400 dan
    // di-encode ulang ke JPG (membuang metadata/EXIF serta isi aneh di dalam file).
    // ==========================================

    /** Menyajikan foto profil milik pengguna yang sedang login. */
    public function foto()
    {
        $path = DB::table('users')->where('id_user', session('id_user'))->value('foto_profil');
        abort_if(!$path || !str_starts_with($path, 'profil/'), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function updateFoto(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk lagi.']);
        }

        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'foto.required' => 'Pilih foto terlebih dahulu.',
            'foto.image'    => 'File harus berupa foto.',
            'foto.mimes'    => 'Format foto harus JPG, PNG, atau WEBP.',
            'foto.max'      => 'Ukuran foto maksimal 5 MB.',
        ]);

        $userId = (int) session('id_user');
        $file = $request->file('foto');

        $hasil = $this->perkecilFoto($file->getRealPath());
        if ($hasil === false) {
            return redirect()->route('profil.index')->withErrors(['foto' => 'Foto tidak bisa diproses. Coba foto lain.']);
        }

        $disk = Storage::disk('local');
        $dasar = 'profil/' . $userId . '_' . time() . '_' . Str::random(6);
        if ($hasil === null) {
            // Ekstensi GD tidak tersedia di server: simpan apa adanya
            $path = $dasar . '.' . $file->extension();
            $disk->putFileAs('profil', $file, basename($path));
        } else {
            $path = $dasar . '.jpg';
            $disk->put($path, $hasil);
        }

        $lama = DB::table('users')->where('id_user', $userId)->value('foto_profil');
        DB::table('users')->where('id_user', $userId)->update(['foto_profil' => $path]);
        $this->hapusBerkasFoto($lama);

        session(['foto_profil' => $path]);

        return redirect()->route('profil.index')->with('success', 'Foto profil diperbarui.');
    }

    public function hapusFoto()
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk lagi.']);
        }

        $userId = (int) session('id_user');
        $lama = DB::table('users')->where('id_user', $userId)->value('foto_profil');

        DB::table('users')->where('id_user', $userId)->update(['foto_profil' => null]);
        $this->hapusBerkasFoto($lama);

        session(['foto_profil' => '']);

        return redirect()->route('profil.index')->with('success', 'Foto profil dihapus.');
    }

    private function hapusBerkasFoto(?string $path): void
    {
        if ($path && str_starts_with($path, 'profil/')) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Potong tengah jadi persegi, kecilkan ke 400x400, simpan sebagai JPG (kualitas 85).
     * Mengembalikan: string JPG | null (GD tidak ada) | false (gambar tidak bisa dibaca).
     */
    private function perkecilFoto(string $path): string|false|null
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }

        $data = @file_get_contents($path);
        $src = $data !== false ? @imagecreatefromstring($data) : false;
        if (!$src) {
            return false;
        }

        // Putar sesuai orientasi kamera HP (EXIF akan hilang setelah di-encode ulang)
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $arah = (int) ($exif['Orientation'] ?? 1);
            $sudut = [3 => 180, 6 => -90, 8 => 90][$arah] ?? 0;
            if ($sudut !== 0 && function_exists('imagerotate')) {
                $diputar = @imagerotate($src, $sudut, 0);
                if ($diputar) {
                    $src = $diputar;
                }
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $sisi = min($w, $h);
        $x = intdiv($w - $sisi, 2);
        $y = intdiv($h - $sisi, 2);

        $dst = imagecreatetruecolor(400, 400);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG transparan
        imagecopyresampled($dst, $src, 0, 0, $x, $y, 400, 400, $sisi, $sisi);

        ob_start();
        imagejpeg($dst, null, 85);
        $jpg = ob_get_clean();

        return $jpg ?: false;
    }

    public function updatePassword(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk lagi.']);
        }

        $userId = session('id_user');

        $request->validate([
            'password_lama' => 'required',
            'password_baru' => 'required|min:8|confirmed',
        ], [
            'password_baru.min' => 'Kata sandi baru terlalu pendek! Minimal 8 karakter.',
            'password_baru.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
        ]);

        $user = DB::table('users')->where('id_user', $userId)->first();

        if (!$user || !Hash::check($request->password_lama, $user->password_hash)) {
            return redirect()->route('profil.index')->withErrors(['password_lama' => 'Kata sandi lama yang Anda masukkan salah.']);
        }

        $sekarang = now();

        DB::table('users')->where('id_user', $userId)->update([
            'password_hash' => Hash::make($request->password_baru),
            'password_changed_at' => $sekarang,   // sesi di perangkat lain otomatis dikeluarkan
        ]);

        // Sesi yang sedang dipakai tetap berlaku
        session(['login_at' => $sekarang->timestamp]);

        return redirect()->route('profil.index')->with('success', 'Kata sandi diubah.');
    }

    // ==========================================
    // EMAIL PEMULIHAN (dipakai untuk Lupa Kata Sandi)
    // Mengganti email WAJIB memasukkan kata sandi + kode OTP yang dikirim ke email baru.
    // ==========================================

    public function kirimKodeEmail(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk lagi.']);
        }

        $userId = (int) session('id_user');

        $request->validate([
            'email'    => 'required|email:rfc|max:150',
            'password' => 'required',
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Masukkan kata sandi Anda untuk konfirmasi.',
        ]);

        // Maksimal 5 permintaan per jam (sekaligus membatasi tebakan kata sandi dari sesi yang dibajak)
        $kunci = 'email-kode:' . $userId;
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $menit = max(1, (int) ceil(RateLimiter::availableIn($kunci) / 60));
            return redirect()->route('profil.index')
                ->withErrors(['email' => "Terlalu banyak permintaan. Coba lagi dalam {$menit} menit."]);
        }
        RateLimiter::hit($kunci, 3600);

        $user = DB::table('users')->where('id_user', $userId)->first();
        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return redirect()->route('profil.index')
                ->withErrors(['password' => 'Kata sandi yang Anda masukkan salah.'])
                ->withInput($request->only('email'));
        }

        $email = Str::lower(trim($request->email));
        $kode = $this->otp()->buat($userId, OtpService::TUJUAN_EMAIL, $email);
        $this->otp()->kirimKodeEmail($email, $user->nama_lengkap, $kode);

        return redirect()->route('profil.index')
            ->with('success', "Kode verifikasi dikirim ke {$email}.");
    }

    public function verifikasiEmail(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk lagi.']);
        }

        $userId = (int) session('id_user');

        $request->validate([
            'kode' => 'required|string|max:12',
        ], [
            'kode.required' => 'Kode wajib diisi.',
        ]);

        $kode = preg_replace('/\D+/', '', (string) $request->kode);

        $status = 'salah';
        $baris = null;
        if (strlen($kode) === 6) {
            [$status, $baris] = $this->otp()->periksa($userId, OtpService::TUJUAN_EMAIL, $kode);
        }

        if ($status !== 'ok') {
            return redirect()->route('profil.index')->withErrors([
                'kode' => 'Kode salah, sudah kedaluwarsa, atau terkunci karena terlalu banyak percobaan. Kirim kode baru bila perlu.',
            ]);
        }

        $lama = DB::table('users')->where('id_user', $userId)->first();

        DB::table('users')->where('id_user', $userId)->update([
            'email'             => $baris->email,
            'email_verified_at' => now(),
        ]);

        // Kalau sebelumnya sudah ada email terverifikasi yang berbeda, beri tahu email lama
        if ($lama && !empty($lama->email) && !empty($lama->email_verified_at)
            && Str::lower($lama->email) !== $baris->email) {
            $this->otp()->kirimNotifikasi($lama->email, $lama->nama_lengkap, 'Email pemulihan akun diganti', [
                'Email pemulihan akun SCP Anda (NIS / NIP: ' . $lama->username . ') diganti ke ' . $baris->email . ' pada ' . now()->format('d-m-Y H:i') . '.',
                'Kalau ini bukan Anda, segera hubungi Waka Kurikulum.',
            ]);
        }

        return redirect()->route('profil.index')->with('success', 'Email pemulihan terverifikasi.');
    }
}
