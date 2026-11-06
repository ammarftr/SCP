<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Role — disederhanakan jadi 5 role sesuai kebutuhan:
        // Waka Kurikulum, Guru, Wali Kelas, Guru BK, Siswa
        Schema::create('role', function (Blueprint $table) {
            $table->increments('id_role');
            $table->string('nama_role', 30);
        });

        DB::table('role')->insert([
            ['id_role' => 2, 'nama_role' => 'Guru'],
            ['id_role' => 3, 'nama_role' => 'Wali Kelas'],
            ['id_role' => 4, 'nama_role' => 'Waka Kurikulum'],
            ['id_role' => 5, 'nama_role' => 'Siswa'],
            ['id_role' => 7, 'nama_role' => 'Guru BK'],
        ]);

        // 2. Users
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id_user');
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255);
            $table->string('nama_lengkap', 100);
            $table->string('no_hp', 20)->nullable();
            $table->unsignedInteger('id_role');
            $table->tinyInteger('is_aktif')->default(1);

            $table->foreign('id_role')->references('id_role')->on('role')->onDelete('cascade');
        });

        // 3. Jurusan
        Schema::create('jurusan', function (Blueprint $table) {
            $table->string('id_jurusan', 10)->primary();
            $table->string('nama_jurusan', 100);
        });

        DB::table('jurusan')->insert([
            ['id_jurusan' => 'RPL', 'nama_jurusan' => 'Rekayasa Perangkat Lunak'],
        ]);

        // 4. Kelas
        Schema::create('kelas', function (Blueprint $table) {
            $table->string('id_kelas', 20)->primary();
            $table->enum('tingkat', ['X', 'XI', 'XII']);
            $table->string('id_jurusan', 10)->nullable();

            $table->foreign('id_jurusan')->references('id_jurusan')->on('jurusan')->onDelete('set null');
        });

        // 5. Tahun Ajaran
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->string('id_tahun_ajaran', 20)->primary();
            $table->string('nama_semester', 50);
            $table->tinyInteger('is_aktif')->default(0);
        });

        // 6. Mata Pelajaran
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->string('id_mapel', 20)->primary();
            $table->string('nama_mapel', 100);
        });

        // 7. Guru
        Schema::create('guru', function (Blueprint $table) {
            $table->increments('id_guru');
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama_guru', 100);
        });

        // 8. Ruangan
        Schema::create('ruangan', function (Blueprint $table) {
            $table->string('id_ruangan', 20)->primary();
            $table->string('nama_ruangan', 50);
            $table->string('jenis', 20)->nullable()->default('Ruangan');
        });

        // 9. Siswa
        Schema::create('siswa', function (Blueprint $table) {
            $table->increments('id_siswa');
            $table->string('nisn', 20)->unique();
            $table->string('nama_siswa', 100);
            $table->string('id_kelas', 20)->nullable();
            $table->enum('status_utama', ['Aktif', 'Lulus', 'Keluar', 'DO'])->default('Aktif');

            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->onDelete('set null');
        });

        // 10. Jadwal Pelajaran
        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->increments('id_jadwal');
            $table->string('id_tahun_ajaran', 20);
            $table->string('id_kelas', 20);
            $table->string('id_mapel', 20);
            $table->unsignedInteger('id_guru');
            $table->string('id_ruangan_default', 20);
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']); // Sabtu libur
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->enum('cycle_type', ['Blok 1', 'Blok 2'])->default('Blok 1');

            // Mempercepat filter/urut di halaman Manajemen Jadwal Waka
            $table->index(['cycle_type', 'hari', 'jam_mulai'], 'jadwal_blok_hari_jam_index');

            $table->foreign('id_tahun_ajaran')->references('id_tahun_ajaran')->on('tahun_ajaran')->onDelete('cascade');
            $table->foreign('id_kelas')->references('id_kelas')->on('kelas')->onDelete('cascade');
            $table->foreign('id_mapel')->references('id_mapel')->on('mata_pelajaran')->onDelete('cascade');
            $table->foreign('id_guru')->references('id_guru')->on('guru')->onDelete('cascade');
            $table->foreign('id_ruangan_default')->references('id_ruangan')->on('ruangan')->onDelete('cascade');
        });

        // 11. Journals — jurnal mengajar guru (dipakai fitur Jurnal & Dashboard Alert)
        Schema::create('journals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('schedule_id');
            $table->date('date');
            $table->text('topic_description');
            $table->string('photo_path', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('schedule_id')->references('id_jadwal')->on('jadwal_pelajaran')->onDelete('cascade');
        });

        // 12. Attendances — presensi hasil sinkronisasi izin & jurnal
        Schema::create('attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('journal_id');
            $table->unsignedInteger('student_id');
            $table->enum('status', ['Hadir', 'Sakit', 'Izin', 'Alfa'])->default('Hadir');

            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('cascade');
            $table->foreign('student_id')->references('id_siswa')->on('siswa')->onDelete('cascade');
        });

        // 13. Leave Requests — pengajuan izin/sakit siswa
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('applicant_id');
            $table->enum('jenis', ['Sakit', 'Izin'])->default('Izin');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('document_path', 255);
            $table->string('status_approval', 50)->default('Pending');
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id_siswa')->on('siswa')->onDelete('cascade');
        });

        // 14. System Configurations — siklus blok aktif & status lock jadwal
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('active_cycle', ['Blok 1', 'Blok 2'])->default('Blok 1');
            $table->boolean('is_schedule_locked')->default(false); // kolom skema soal; diisi otomatis (true bila kedua blok terkunci)
            $table->boolean('is_locked_blok_1')->default(false);   // kunci jadwal per blok
            $table->boolean('is_locked_blok_2')->default(false);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        DB::table('system_configurations')->insert([
            'active_cycle' => 'Blok 1',
            'is_schedule_locked' => false,
            'is_locked_blok_1' => false,
            'is_locked_blok_2' => false,
            'updated_at' => now(),
        ]);

        // 15. Sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 16. Cache — dipakai pembatas percobaan login (CACHE_STORE=database di .env)
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('system_configurations');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('jadwal_pelajaran');
        Schema::dropIfExists('siswa');
        Schema::dropIfExists('ruangan');
        Schema::dropIfExists('guru');
        Schema::dropIfExists('mata_pelajaran');
        Schema::dropIfExists('tahun_ajaran');
        Schema::dropIfExists('kelas');
        Schema::dropIfExists('jurusan');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role');
    }
};
