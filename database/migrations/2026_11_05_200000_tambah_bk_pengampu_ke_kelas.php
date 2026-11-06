<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan setiap kelas ke SATU Guru BK pengampunya (satu BK bisa mengampu banyak kelas).
 * Guru BK (peran 7) hanya boleh mengelola izin/rekap/laporan kelas yang ditautkan di sini.
 * Kalau akun BK dihapus, kolom ini otomatis kosong (kelas tidak ikut terhapus).
 *
 * Aman dijalankan meski kolom/foreign key sudah pernah dibuat manual lewat SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kelas', 'id_bk')) {
            Schema::table('kelas', function (Blueprint $table) {
                $table->unsignedInteger('id_bk')->nullable()->after('id_jurusan');
            });
        }

        try {
            Schema::table('kelas', function (Blueprint $table) {
                $table->foreign('id_bk', 'kelas_id_bk_foreign')->references('id_user')->on('users')->onDelete('set null');
            });
        } catch (\Throwable $e) {
            // Foreign key sudah ada atau tidak bisa dibuat: kolom tetap berfungsi tanpanya.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('kelas', function (Blueprint $table) {
                $table->dropForeign('kelas_id_bk_foreign');
            });
        } catch (\Throwable $e) {
            // Foreign key tidak ada.
        }

        if (Schema::hasColumn('kelas', 'id_bk')) {
            Schema::table('kelas', function (Blueprint $table) {
                $table->dropColumn('id_bk');
            });
        }
    }
};
