<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WakaController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\JadwalPelajaranController;
use App\Http\Controllers\JurnalController;
use App\Http\Controllers\SiswaIzinController;
use App\Http\Controllers\KesiswaanController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\ImporSiswaController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// ==========================================
// ROUTE AUTENTIKASI & LOGOUT
// ==========================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Lupa password (semua role): NIS/NIP -> kode OTP 6 digit lewat email -> atur password baru
Route::get('/lupa-password', [AuthController::class, 'showForgotForm'])->name('password.forgot');
Route::post('/lupa-password', [AuthController::class, 'kirimKode'])->name('password.forgot.verify');
Route::get('/lupa-password/kode', [AuthController::class, 'showKodeForm'])->name('password.kode');
Route::post('/lupa-password/kode', [AuthController::class, 'verifyKode'])->name('password.kode.verify');
Route::post('/lupa-password/kirim-ulang', [AuthController::class, 'kirimUlang'])->name('password.kode.resend');
Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset.save');

// Profil Saya (semua role bisa akses — ganti username, nama, no hp, password)
Route::middleware('peran')->group(function () {
    Route::get('/profil', [ProfileController::class, 'index'])->name('profil.index');
    Route::post('/profil/update', [ProfileController::class, 'update'])->name('profil.update');
    Route::get('/profil/foto', [ProfileController::class, 'foto'])->name('profil.foto');
    Route::post('/profil/foto', [ProfileController::class, 'updateFoto'])->name('profil.foto.update');
    Route::post('/profil/foto/hapus', [ProfileController::class, 'hapusFoto'])->name('profil.foto.hapus');
    Route::post('/profil/password', [ProfileController::class, 'updatePassword'])->name('profil.password');
    Route::post('/profil/email/kirim-kode', [ProfileController::class, 'kirimKodeEmail'])->name('profil.email.kirim');
    Route::post('/profil/email/verifikasi', [ProfileController::class, 'verifikasiEmail'])->name('profil.email.verifikasi');
});


// ==========================================
// ROUTE WAKA KURIKULUM (peran 4)
// Dashboard (termasuk blok aktif & kunci jadwal), rekap jurnal, dan manajemen data master
// (Akun, Kelas, Mata Pelajaran, Ruangan) dan manajemen jadwal
// ==========================================
Route::middleware('peran:4')->group(function () {
    Route::get('/waka/dashboard', [WakaController::class, 'dashboard'])->name('waka.dashboard');
    Route::get('/waka/jadwal', [WakaController::class, 'jadwal'])->name('waka.jadwal');
    Route::redirect('/waka/pengaturan', '/waka/dashboard'); // halaman lama, sekarang menyatu di Beranda
    Route::post('/waka/config/update', [WakaController::class, 'updateConfig'])->name('waka.config.update');
    Route::get('/waka/jurnal/rekap', [JurnalController::class, 'rekapWaka'])->name('waka.jurnal.rekap');

    Route::get('/manajemen-akun', [MasterDataController::class, 'index'])->name('akun.index');
    Route::post('/manajemen-akun/store', [MasterDataController::class, 'storeAkun'])->name('akun.store');
    Route::post('/manajemen-akun/{id}/update', [MasterDataController::class, 'updateAkun'])->name('akun.update');
    Route::post('/manajemen-akun/{id}/delete', [MasterDataController::class, 'destroyAkun'])->name('akun.destroy');

    Route::post('/master/kelas/store', [MasterDataController::class, 'storeKelas'])->name('master.kelas.store');
    Route::post('/master/kelas/{id}/update', [MasterDataController::class, 'updateKelas'])->name('master.kelas.update');
    Route::post('/master/kelas/{id}/delete', [MasterDataController::class, 'destroyKelas'])->name('master.kelas.destroy');

    Route::post('/master/mapel/store', [MasterDataController::class, 'storeMapel'])->name('master.mapel.store');
    Route::post('/master/mapel/{id}/update', [MasterDataController::class, 'updateMapel'])->name('master.mapel.update');
    Route::post('/master/mapel/{id}/delete', [MasterDataController::class, 'destroyMapel'])->name('master.mapel.destroy');

    Route::post('/master/ruangan/store', [MasterDataController::class, 'storeRuangan'])->name('master.ruangan.store');
    Route::post('/master/ruangan/{id}/update', [MasterDataController::class, 'updateRuangan'])->name('master.ruangan.update');
    Route::post('/master/ruangan/{id}/delete', [MasterDataController::class, 'destroyRuangan'])->name('master.ruangan.destroy');

    Route::post('/master/jadwal/{id}/delete', [MasterDataController::class, 'destroyJadwal'])->name('master.jadwal.destroy');

    // Aksi massal (hapus / ubah banyak sekaligus)
    Route::post('/master/akun/massal-hapus', [MasterDataController::class, 'hapusMassalAkun'])->name('master.akun.massal-hapus');
    Route::post('/master/akun/massal-ubah', [MasterDataController::class, 'ubahMassalAkun'])->name('master.akun.massal-ubah');
    Route::post('/master/akun/massal-ubah-terpisah', [MasterDataController::class, 'ubahTerpisahAkun'])->name('master.akun.massal-ubah-terpisah');
    Route::post('/master/kelas/massal-hapus', [MasterDataController::class, 'hapusMassalKelas'])->name('master.kelas.massal-hapus');
    Route::post('/master/kelas/massal-ubah', [MasterDataController::class, 'ubahMassalKelas'])->name('master.kelas.massal-ubah');
    Route::post('/master/mapel/massal-hapus', [MasterDataController::class, 'hapusMassalMapel'])->name('master.mapel.massal-hapus');
    Route::post('/master/ruangan/massal-hapus', [MasterDataController::class, 'hapusMassalRuangan'])->name('master.ruangan.massal-hapus');
    Route::post('/master/ruangan/massal-ubah', [MasterDataController::class, 'ubahMassalRuangan'])->name('master.ruangan.massal-ubah');
    Route::post('/master/jadwal/massal-hapus', [MasterDataController::class, 'hapusMassalJadwal'])->name('master.jadwal.massal-hapus');

    // Kenaikan kelas (pratinjau, proses, batalkan proses terakhir)
    Route::get('/waka/kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('waka.kenaikan.index');
    Route::post('/waka/kenaikan-kelas/proses', [KenaikanKelasController::class, 'proses'])->name('waka.kenaikan.proses');
    Route::post('/waka/kenaikan-kelas/{id}/batalkan', [KenaikanKelasController::class, 'batalkan'])->name('waka.kenaikan.batalkan');

    // Impor siswa massal (tempel dari Excel)
    Route::get('/waka/impor-siswa', [ImporSiswaController::class, 'index'])->name('waka.impor.index');
    Route::post('/waka/impor-siswa/pratinjau', [ImporSiswaController::class, 'pratinjau'])->name('waka.impor.pratinjau');
    Route::post('/waka/impor-siswa/simpan', [ImporSiswaController::class, 'simpan'])->name('waka.impor.simpan');

});


// ==========================================
// ROUTE GURU (peran 2) + WAKA YANG MENGAJAR (peran 4) + GURU BK (peran 7)
// BK dan Waka yang juga guru mapel dijadwalkan seperti guru biasa, jadi memakai
// beranda, jadwal, dan jurnal yang sama (data tetap dibatasi ke jadwal miliknya).
// ==========================================
Route::middleware('peran:2,4,7')->group(function () {
    Route::get('/guru/dashboard', [GuruController::class, 'dashboard'])->name('guru.dashboard');
});


// ==========================================
// ROUTE JADWAL & JURNAL (khusus staf: Guru, Wali Kelas, Waka, Guru BK; siswa tidak punya akses)
// Pembatasan lanjutan (hanya jadwal milik sendiri, status kunci) ada di controller.
// ==========================================
Route::middleware('peran:2,3,4,7')->group(function () {
    Route::get('/jadwal', [JadwalPelajaranController::class, 'index'])->name('jadwal.index');
    Route::get('/jadwal/ketersediaan', [JadwalPelajaranController::class, 'ketersediaan'])->name('jadwal.ketersediaan');
    Route::post('/jadwal/store', [JadwalPelajaranController::class, 'store'])->name('jadwal.store');
    Route::post('/jadwal/{id}/update', [JadwalPelajaranController::class, 'update'])->name('jadwal.update');
    Route::post('/jadwal/{id}/delete', [JadwalPelajaranController::class, 'destroy'])->name('jadwal.destroy');

    Route::get('/jurnal', [JurnalController::class, 'index'])->name('jurnal.index');
    Route::post('/jurnal/store', [JurnalController::class, 'store'])->name('jurnal.store');
    Route::post('/jurnal/{id}/presensi', [JurnalController::class, 'ubahPresensi'])->name('jurnal.presensi.ubah');
});


// ==========================================
// ROUTE SISWA (PENGAJUAN IZIN / SAKIT) (peran 5)
// ==========================================
Route::middleware('peran:5')->group(function () {
    Route::get('/siswa/izin', [SiswaIzinController::class, 'index'])->name('siswa.izin.index');
    Route::post('/siswa/izin', [SiswaIzinController::class, 'store'])->name('siswa.izin.store');
});


// ==========================================
// ROUTE KESISWAAN (WALI KELAS & GURU BK) - VERIFIKASI IZIN (peran 3 dan 7)
// ==========================================
Route::middleware('peran:3,7')->group(function () {
    Route::get('/kesiswaan/izin', [KesiswaanController::class, 'index'])->name('kesiswaan.izin.index');
    Route::post('/kesiswaan/izin/{id}/update', [KesiswaanController::class, 'updateStatus'])->name('kesiswaan.izin.update');
    Route::get('/kesiswaan/rekap-absensi', [KesiswaanController::class, 'rekapAbsensi'])->name('kesiswaan.rekap.absensi');
    Route::get('/kesiswaan/laporan-izin', [KesiswaanController::class, 'laporanIzinHarian'])->name('kesiswaan.laporan.izin');
});


// ==========================================
// BERKAS UNGGAHAN (hak akses diperiksa di BerkasController)
// ==========================================
Route::get('/berkas/izin/{id}', [BerkasController::class, 'izin'])->middleware('peran:3,4,5,7')->name('berkas.izin');
Route::get('/berkas/jurnal/{id}', [BerkasController::class, 'jurnal'])->middleware('peran:2,4,7')->name('berkas.jurnal');
