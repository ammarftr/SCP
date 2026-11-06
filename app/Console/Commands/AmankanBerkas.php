<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AmankanBerkas extends Command
{
    protected $signature = 'berkas:amankan';

    protected $description = 'Pindahkan surat izin dan foto jurnal lama dari folder publik ke penyimpanan privat';

    public function handle(): int
    {
        $jumlah = 0;

        $sasaran = [
            ['leave_requests', 'document_path', 'izin'],
            ['journals', 'photo_path', 'jurnal'],
        ];

        foreach ($sasaran as [$tabel, $kolom, $folder]) {
            $baris = DB::table($tabel)->where($kolom, 'like', 'upload%')->get(['id', $kolom]);

            foreach ($baris as $b) {
                $lama = public_path($b->$kolom);

                if (!is_file($lama)) {
                    $this->warn("Berkas tidak ditemukan, dilewati: {$b->$kolom}");
                    continue;
                }

                $baru = $folder . '/' . basename($lama);
                Storage::disk('local')->put($baru, file_get_contents($lama));
                DB::table($tabel)->where('id', $b->id)->update([$kolom => $baru]);
                @unlink($lama);
                $jumlah++;
            }
        }

        $this->info("Selesai: {$jumlah} berkas dipindahkan ke penyimpanan privat.");

        return self::SUCCESS;
    }
}
