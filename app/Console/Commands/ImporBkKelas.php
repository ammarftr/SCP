<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImporBkKelas extends Command
{
    protected $signature = 'bk:impor-kelas
                            {--berkas=database/data/wali_bk_2026_2027.json : JSON daftar kelas & nama Guru BK (dari WALI_KELAS_2026_2027.xlsx)}
                            {--simulasi : Hanya tampilkan hasil pencocokan, tanpa menyimpan}';

    protected $description = 'Tetapkan Guru BK pengampu tiap kelas (kelas.id_bk) berdasarkan daftar Wali Kelas & Guru BK';

    public function handle(): int
    {
        $path = base_path($this->option('berkas'));
        if (!is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $baris = json_decode(file_get_contents($path), true) ?: [];

        // Kunci pencocokan: kelas = tanpa spasi & huruf besar; nama = bagian sebelum koma, huruf kecil saja
        $kelasDb = DB::table('kelas')->pluck('id_kelas')
            ->mapWithKeys(fn($id) => [$this->kunciKelas($id) => $id]);
        $bkDb = DB::table('users')->where('id_role', 7)->get(['id_user', 'nama_lengkap'])
            ->mapWithKeys(fn($u) => [$this->kunciNama($u->nama_lengkap) => $u]);

        $tanpaKelas = [];
        $tanpaBk = [];
        $cocok = 0;

        DB::transaction(function () use ($baris, $kelasDb, $bkDb, &$tanpaKelas, &$tanpaBk, &$cocok) {
            foreach ($baris as $b) {
                $idKelas = $kelasDb[$this->kunciKelas($b['kelas'])] ?? null;
                if ($idKelas === null) {
                    $tanpaKelas[] = $b['kelas'];
                    continue;
                }

                $bk = $bkDb[$this->kunciNama($b['bk'])] ?? null;
                if ($bk === null) {
                    $tanpaBk[$b['bk']][] = $idKelas;
                    continue;
                }

                if (!$this->option('simulasi')) {
                    DB::table('kelas')->where('id_kelas', $idKelas)->update(['id_bk' => $bk->id_user]);
                }
                $cocok++;
            }
        });

        $this->info(($this->option('simulasi') ? '[SIMULASI] ' : '') . "{$cocok} kelas dicocokkan dengan Guru BK.");

        if ($tanpaKelas) {
            $this->warn(count($tanpaKelas) . ' kelas di daftar belum ada di database (tambahkan lewat Manajemen Kelas): ' . implode(', ', $tanpaKelas));
        }
        foreach ($tanpaBk as $nama => $kelas) {
            $this->warn("Akun Guru BK \"{$nama}\" belum ada (peran Guru BK). Kelas terkait: " . implode(', ', $kelas));
        }

        return self::SUCCESS;
    }

    private function kunciKelas(string $nilai): string
    {
        return strtoupper(preg_replace('/\s+/', '', $nilai));
    }

    private function kunciNama(string $nilai): string
    {
        $inti = trim(explode(',', $nilai)[0]);

        return strtolower(preg_replace('/[^A-Za-z]/', '', $inti));
    }
}
