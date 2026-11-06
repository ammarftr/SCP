<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Email pemulihan: baru dipakai untuk atur ulang kata sandi setelah email_verified_at terisi
            $table->string('email', 150)->nullable()->after('no_hp');
            $table->dateTime('email_verified_at')->nullable()->after('email');
            // Sesi yang dibuat sebelum waktu ini otomatis dikeluarkan (lihat PastikanPeran)
            $table->dateTime('password_changed_at')->nullable()->after('email_verified_at');
        });

        // Kode OTP sekali pakai. Yang disimpan hanya hash-nya, bukan kode aslinya.
        Schema::create('kode_otp', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_user');
            $table->string('tujuan', 20);            // 'reset' = lupa kata sandi, 'email' = verifikasi email baru
            $table->string('email', 150);            // alamat tujuan pengiriman kode
            $table->string('kode_hash', 64);
            $table->dateTime('kedaluwarsa_pada');
            $table->unsignedTinyInteger('percobaan')->default(0);
            $table->dateTime('dipakai_pada')->nullable();   // terisi = sudah dipakai / dibatalkan
            $table->dateTime('created_at');

            $table->index(['id_user', 'tujuan']);
            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_otp');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at', 'password_changed_at']);
        });
    }
};
