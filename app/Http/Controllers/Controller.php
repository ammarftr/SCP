<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Batas data untuk Tim Kesiswaan.
     *  - Wali Kelas (peran 3)  -> hanya kelas perwaliannya (string id_kelas),
     *                             atau '' bila belum ditetapkan Waka (tidak melihat apa pun).
     *  - Guru (peran 2) yang merangkap wali kelas -> sama seperti Wali Kelas.
     *  - Guru BK (peran 7)     -> null di sini; batasnya ada di batasKelas() (kelas yang diampu).
     */
    protected function kelasPerwalian(): ?string
    {
        // Wali Kelas (3) dan Guru yang merangkap Wali Kelas (2 + kelas perwalian terisi)
        if (!in_array((int) session('id_role'), [2, 3], true)) {
            return null;
        }

        return (string) DB::table('users')
            ->where('id_user', session('id_user'))
            ->value('id_kelas_wali');
    }

    /**
     * Daftar id_kelas yang diampu Guru BK yang sedang login (kelas.id_bk = id_user),
     * ditambah kelas perwaliannya bila BK juga merangkap wali kelas.
     */
    protected function kelasBk(): array
    {
        $idUser = session('id_user');

        $kelas = DB::table('kelas')->where('id_bk', $idUser)->pluck('id_kelas')->all();

        $wali = DB::table('users')->where('id_user', $idUser)->value('id_kelas_wali');
        if ($wali) {
            $kelas[] = $wali;
        }

        return array_values(array_unique(array_map('strval', $kelas)));
    }

    /**
     * Batas kelas untuk Tim Kesiswaan dalam bentuk daftar.
     *  - Wali Kelas / Guru merangkap wali -> [kelas perwalian] (kosong bila belum ditetapkan)
     *  - Guru BK                          -> kelas yang diampu (kosong bila belum ada)
     *  - Peran lain                       -> null = tanpa batas
     */
    protected function batasKelas(): ?array
    {
        $peran = (int) session('id_role');

        if ($peran === 7) {
            return $this->kelasBk();
        }

        $wali = $this->kelasPerwalian();
        if ($wali === null) {
            return null;
        }

        return $wali !== '' ? [$wali] : [];
    }

    /**
     * Aplikasi tidak lagi memakai konsep semester. Kolom jadwal_pelajaran.id_tahun_ajaran
     * masih ada di database (wajib terisi), jadi jadwal baru otomatis memakai satu
     * baris bawaan: yang sudah ada, atau "Umum" bila tabelnya masih kosong.
     */
    protected function tahunAjaranBawaan(): string
    {
        $id = DB::table('tahun_ajaran')->orderByDesc('is_aktif')->orderBy('id_tahun_ajaran')->value('id_tahun_ajaran');
        if ($id === null) {
            DB::table('tahun_ajaran')->insert(['id_tahun_ajaran' => 'Umum', 'nama_semester' => 'Umum', 'is_aktif' => 1]);
            $id = 'Umum';
        }

        return (string) $id;
    }

    /**
     * Ambil data guru milik akun yang sedang login (nip = username).
     * Kalau akunnya berperan guru/tenaga pendidik tapi baris di tabel `guru`
     * belum ada (mis. role diubah dari Siswa ke Guru), barisnya dibuat otomatis
     * supaya jadwal & jurnal tetap bisa dipakai.
     */
    protected function currentGuru(): ?Guru
    {
        $username = session('username');
        if (!$username) {
            return null;
        }

        $guru = Guru::where('nip', $username)->first();
        if ($guru) {
            return $guru;
        }

        if (in_array((int) session('id_role'), [2, 3, 4, 7], true)) {
            $user = DB::table('users')->where('username', $username)->first();
            if ($user) {
                $id = DB::table('guru')->insertGetId([
                    'nip'       => $user->username,
                    'nama_guru' => $user->nama_lengkap,
                ]);
                return Guru::find($id);
            }
        }

        return null;
    }
}
