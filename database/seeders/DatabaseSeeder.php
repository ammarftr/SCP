<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Semua akun uji coba menggunakan password: 12345678 (minimal 8 karakter, sesuai aturan login)
     */
    public function run(): void
    {
        $password = Hash::make('12345678');

        // Master ruangan & mapel & kelas minimal (kalau belum ada dari migration)
        DB::table('ruangan')->insertOrIgnore([
            ['id_ruangan' => '1', 'nama_ruangan' => 'R 01', 'jenis' => 'Ruangan'],
            ['id_ruangan' => '2', 'nama_ruangan' => 'R 02', 'jenis' => 'Ruangan'],
            ['id_ruangan' => '31', 'nama_ruangan' => 'L 01 (Lab RPL 1)', 'jenis' => 'Lab'],
        ]);

        DB::table('mata_pelajaran')->insertOrIgnore([
            ['id_mapel' => 'MTK', 'nama_mapel' => 'Matematika'],
            ['id_mapel' => 'PAI', 'nama_mapel' => 'Pendidikan Agama dan Budi Pekerti'],
            ['id_mapel' => 'KK1', 'nama_mapel' => 'KK 1 RPL Basis Data'],
        ]);

        DB::table('jurusan')->insertOrIgnore([
            ['id_jurusan' => 'RPL', 'nama_jurusan' => 'Rekayasa Perangkat Lunak'],
        ]);

        DB::table('kelas')->insertOrIgnore([
            ['id_kelas' => 'X RPL 1', 'tingkat' => 'X', 'id_jurusan' => 'RPL'],
        ]);

        DB::table('tahun_ajaran')->insertOrIgnore([
            ['id_tahun_ajaran' => '2026 Ganjil', 'nama_semester' => '2026 Ganjil', 'is_aktif' => 1],
        ]);

        // Guru (dipakai untuk akun Guru & Guru BK — nip = username)
        DB::table('guru')->insertOrIgnore([
            ['id_guru' => 1, 'nip' => 'guru', 'nama_guru' => 'Guru Uji Coba'],
        ]);

        // Siswa (dipakai untuk akun Siswa — nisn = username), sekalian isi 1 kelas biar rekap absensi kelihatan
        DB::table('siswa')->insertOrIgnore([
            ['id_siswa' => 1, 'nisn' => 'siswa', 'nama_siswa' => 'Siswa Uji Coba', 'id_kelas' => 'X RPL 1', 'status_utama' => 'Aktif'],
            ['id_siswa' => 2, 'nisn' => '0011', 'nama_siswa' => 'Ahmad Fadli', 'id_kelas' => 'X RPL 1', 'status_utama' => 'Aktif'],
            ['id_siswa' => 3, 'nisn' => '0012', 'nama_siswa' => 'Bunga Citra', 'id_kelas' => 'X RPL 1', 'status_utama' => 'Aktif'],
        ]);

        // Akun uji coba untuk setiap role — password semuanya "12345678"
        $testUsers = [
            ['username' => 'waka',   'nama_lengkap' => 'Waka Kurikulum (Uji Coba)', 'id_role' => 4],
            ['username' => 'guru',   'nama_lengkap' => 'Guru Uji Coba',             'id_role' => 2],
            ['username' => 'walkel', 'nama_lengkap' => 'Wali Kelas (Uji Coba)',     'id_role' => 3],
            ['username' => 'bk',     'nama_lengkap' => 'Guru BK (Uji Coba)',        'id_role' => 7],
            ['username' => 'siswa',  'nama_lengkap' => 'Siswa Uji Coba',            'id_role' => 5],
        ];

        foreach ($testUsers as $u) {
            DB::table('users')->updateOrInsert(
                ['username' => $u['username']],
                [
                    'password_hash' => $password,
                    'nama_lengkap' => $u['nama_lengkap'],
                    'no_hp' => null,
                    'id_role' => $u['id_role'],
                    'is_aktif' => 1,
                ]
            );
        }

        // Wali kelas uji coba memegang X RPL 1 (hanya melihat siswa kelas itu)
        DB::table('users')->where('username', 'walkel')->update(['id_kelas_wali' => 'X RPL 1']);

        // Jadwal contoh hari ini, jam selesai sudah lewat 30+ menit -> buat nge-tes Dashboard Alert
        $today = now()->translatedFormat('l');
        $hariMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Senin', 'Sunday' => 'Senin', // akhir pekan libur: contoh dipasang di hari Senin
        ];
        $hariIni = $hariMap[now()->format('l')] ?? 'Senin';

        DB::table('jadwal_pelajaran')->insertOrIgnore([
            'id_jadwal' => 999,
            'id_tahun_ajaran' => '2026 Ganjil',
            'id_kelas' => 'X RPL 1',
            'id_mapel' => 'MTK',
            'id_guru' => 1,
            'id_ruangan_default' => '1',
            'hari' => $hariIni,
            'jam_mulai' => now()->subHours(2)->format('H:i:s'),
            'jam_selesai' => now()->subHour()->format('H:i:s'),
            'cycle_type' => 'Blok 1',
        ]);
    }
}
