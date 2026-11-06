<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto profil pengguna. Disimpan di penyimpanan privat (storage/app/private/profil),
 * kolom ini hanya berisi path relatifnya (mis. "profil/12_1730000000_ab12cd.jpg").
 * Aman dijalankan ulang bila kolom sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'foto_profil')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('foto_profil', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'foto_profil')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('foto_profil');
            });
        }
    }
};
