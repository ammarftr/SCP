<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat proses Kenaikan Kelas.
 *
 * kenaikan_kelas        : satu baris per proses (siapa, kapan, berapa siswa).
 * kenaikan_kelas_detail : kondisi tiap siswa sebelum & sesudah proses, dipakai tombol "Batalkan".
 *
 * Sengaja tanpa foreign key ke siswa/users: riwayat harus tetap ada walau siswanya dihapus kemudian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kenaikan_kelas')) {
            Schema::create('kenaikan_kelas', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('id_user')->nullable();
                $table->string('nama_pembuat', 100)->nullable();
                $table->unsignedInteger('jumlah_naik')->default(0);
                $table->unsignedInteger('jumlah_lulus')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('dibatalkan_pada')->nullable();
            });
        }

        if (!Schema::hasTable('kenaikan_kelas_detail')) {
            Schema::create('kenaikan_kelas_detail', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('id_kenaikan');
                $table->unsignedInteger('id_siswa');
                $table->string('kelas_asal', 20)->nullable();
                $table->string('kelas_tujuan', 20)->nullable();
                $table->string('status_asal', 10);
                $table->string('status_baru', 10);
                $table->unsignedInteger('id_user_dinonaktifkan')->nullable();

                $table->index('id_kenaikan');
                $table->foreign('id_kenaikan')->references('id')->on('kenaikan_kelas')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kenaikan_kelas_detail');
        Schema::dropIfExists('kenaikan_kelas');
    }
};
