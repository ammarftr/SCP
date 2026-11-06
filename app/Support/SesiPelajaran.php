<?php

namespace App\Support;

/** Pembantu untuk sesi (jam ke-) pelajaran per hari. Data ada di config/sesi.php. */
class SesiPelajaran
{
    /** Daftar sesi satu hari: [['no'=>1,'mulai'=>'07:15','selesai'=>'07:55','kelompok'=>0], ...] */
    public static function untukHari(?string $hari): array
    {
        $baris = config('sesi.hari')[$hari] ?? [];

        return array_map(fn ($s) => [
            'no' => $s[0], 'mulai' => $s[1], 'selesai' => $s[2], 'kelompok' => $s[3],
        ], $baris);
    }

    /** Semua hari, untuk dipakai JavaScript di tampilan. */
    public static function untukJs(): array
    {
        $hasil = [];
        foreach (array_keys(config('sesi.hari')) as $hari) {
            $hasil[$hari] = self::untukHari($hari);
        }

        return $hasil;
    }

    /**
     * Ubah rentang sesi jadi jam mulai/selesai.
     * Mengembalikan ['mulai'=>..., 'selesai'=>...] atau string pesan galat.
     */
    public static function hitung(string $hari, int $sesiMulai, int $sesiSelesai): array|string
    {
        $sesi = collect(self::untukHari($hari))->keyBy('no');
        $awal = $sesi->get($sesiMulai);
        $akhir = $sesi->get($sesiSelesai);

        if (!$awal || !$akhir) {
            return 'Jam ke- yang dipilih tidak tersedia pada hari ' . $hari . '.';
        }
        if ($sesiSelesai < $sesiMulai) {
            return 'Sesi selesai harus sama dengan atau setelah sesi mulai.';
        }
        if ($awal['kelompok'] !== $akhir['kelompok']) {
            return 'Jadwal tidak boleh melewati waktu istirahat. Pilih sesi dalam satu blok jam.';
        }

        return ['mulai' => $awal['mulai'], 'selesai' => $akhir['selesai']];
    }

    /** Label seperti "Jam ke-1–2" dari jam mulai/selesai yang tersimpan; null bila tidak cocok sesi. */
    public static function label(?string $hari, ?string $mulai, ?string $selesai): ?string
    {
        $sesi = collect(self::untukHari($hari));
        $a = $sesi->firstWhere('mulai', substr((string) $mulai, 0, 5));
        $b = $sesi->firstWhere('selesai', substr((string) $selesai, 0, 5));
        if (!$a || !$b || $b['no'] < $a['no']) {
            return null;
        }

        return $a['no'] === $b['no'] ? 'Jam ke-' . $a['no'] : 'Jam ke-' . $a['no'] . '–' . $b['no'];
    }
}
