<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak koreksi presensi: saat izin yang terlambat diajukan di-Approve, presensi
 * yang sudah terlanjur Alfa ditimpa jadi Sakit/Izin. Status asli (Alfa) disimpan
 * di sini supaya bisa dikembalikan dan bisa diaudit (siapa, kapan, dari izin mana).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status_asal', ['Hadir', 'Sakit', 'Izin', 'Alfa'])->nullable();
            $table->unsignedInteger('leave_request_id')->nullable();
            $table->unsignedInteger('dikoreksi_oleh')->nullable();
            $table->timestamp('dikoreksi_pada')->nullable();

            $table->foreign('leave_request_id')->references('id')->on('leave_requests')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['leave_request_id']);
            $table->dropColumn(['status_asal', 'leave_request_id', 'dikoreksi_oleh', 'dikoreksi_pada']);
        });
    }
};
