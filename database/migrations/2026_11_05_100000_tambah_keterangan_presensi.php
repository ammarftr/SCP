<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keterangan presensi: catatan singkat guru untuk siswa yang tidak hadir
 * (mis. "Rapat OSIS", "Sakit demam"). Kosong untuk siswa yang hadir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('keterangan', 255)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
