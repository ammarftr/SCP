<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan akun Wali Kelas (id_role = 3) ke SATU kelas perwaliannya.
 * - nullable : akun non-wali kelas tidak punya kelas perwalian
 * - unique   : satu kelas hanya punya satu wali kelas
 * - FK       : kalau kelas dihapus, perwalian otomatis kosong (bukan ikut terhapus)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_kelas_wali', 20)->nullable()->unique()->after('is_aktif');
            $table->foreign('id_kelas_wali')->references('id_kelas')->on('kelas')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_kelas_wali']);
            $table->dropUnique(['id_kelas_wali']);
            $table->dropColumn('id_kelas_wali');
        });
    }
};
