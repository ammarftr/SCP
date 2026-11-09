<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint soal: attendances punya kolom `date` (NOT NULL).
 * Tanggal presensi = tanggal jurnal; baris lama diisi dari journals.date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendances', 'date')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->date('date')->nullable()->after('status');
            });
        }

        DB::statement('UPDATE attendances a JOIN journals j ON j.id = a.journal_id SET a.date = j.date WHERE a.date IS NULL');

        Schema::table('attendances', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
