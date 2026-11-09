<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Impor siswa massal dengan cara menempel (copy-paste) dua kolom dari Excel: NIS dan Nama.
 *
 * Kenapa cepat:
 *  - kata sandi awal sama untuk semua siswa, jadi bcrypt dihitung SEKALI (bukan per siswa)
 *  - data disimpan dengan insert per potongan (chunk), bukan satu per satu
 *  - tidak ada pembacaan file Excel (tanpa library tambahan)
 *
 * Akun yang dibuat sama dengan Manajemen Data > Akun > Siswa:
 *  users (username = NIS, id_role = 5) + siswa (nisn = NIS, kelas dipilih, status Aktif).
 */
class ImporSiswaController extends Controller
{
    private const SANDI_AWAL = '12345678';
    private const MAKS_BARIS = 1000;

    public function index()
    {
        return $this->tampil(null, '', null);
    }

    public function pratinjau(Request $request)
    {
        $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'teks'     => 'required|string',
        ], [
            'id_kelas.required' => 'Pilih kelas tujuan dulu.',
            'teks.required'     => 'Tempel data NIS dan nama dari Excel dulu.',
        ]);

        $teks = (string) $request->input('teks');
        $hasil = $this->analisis($teks);

        return $this->tampil($hasil, $teks, $request->input('id_kelas'));
    }

    public function simpan(Request $request)
    {
        $request->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'teks'     => 'required|string',
        ]);

        // Analisis ulang dari teks yang sama (data di database bisa berubah sejak pratinjau)
        $hasil = $this->analisis((string) $request->input('teks'));
        $valid = $hasil['valid'];

        if (!$valid) {
            return redirect()->route('waka.impor.index')->withErrors(['error' => 'Tidak ada baris yang valid untuk disimpan.']);
        }

        $idKelas = $request->input('id_kelas');
        $hash = bcrypt(self::SANDI_AWAL); // sekali saja, dipakai semua siswa

        try {
            DB::transaction(function () use ($valid, $idKelas, $hash) {
                foreach (array_chunk($valid, 200) as $potong) {
                    DB::table('users')->insert(array_map(fn($r) => [
                        'username'      => $r['nis'],
                        'password_hash' => $hash,
                        'nama_lengkap'  => $r['nama'],
                        'no_hp'         => null,
                        'id_role'       => 5,
                        'is_aktif'      => 1,
                    ], $potong));

                    DB::table('siswa')->insert(array_map(fn($r) => [
                        'nisn'         => $r['nis'],
                        'nama_siswa'   => $r['nama'],
                        'id_kelas'     => $idKelas,
                        'status_utama' => 'Aktif',
                    ], $potong));
                }
            });
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('waka.impor.index')
                ->withErrors(['error' => 'Impor gagal dan tidak ada data yang tersimpan. Pratinjau ulang lalu coba lagi.']);
        }

        $lewat = count($hasil['ada']) + count($hasil['ganda']) + count($hasil['rusak']);
        $pesan = count($valid) . " siswa berhasil diimpor ke kelas {$idKelas}. Kata sandi awal: " . self::SANDI_AWAL . '.';
        if ($lewat) {
            $pesan .= " {$lewat} baris dilewati.";
        }

        return redirect()->route('waka.impor.index')->with('success', $pesan);
    }

    private function tampil($hasil, string $teks, $idKelas)
    {
        return view('waka.impor_siswa', [
            'kelas'    => DB::table('kelas')->orderBy('id_kelas')->get(),
            'hasil'    => $hasil,
            'teks'     => $teks,
            'idKelas'  => $idKelas,
            'sandi'    => self::SANDI_AWAL,
            'maks'     => self::MAKS_BARIS,
        ]);
    }

    /**
     * Pecah teks tempelan menjadi baris valid, dan kelompokkan yang bermasalah.
     *
     * @return array{valid: array, ada: array, ganda: array, rusak: array, terpotong: bool}
     */
    private function analisis(string $teks): array
    {
        $teks = preg_replace('/^\xEF\xBB\xBF/', '', $teks); // BOM
        $baris = preg_split('/\R/u', $teks);

        $kandidat = [];
        $rusak = [];
        $ganda = [];
        $terpotong = false;
        $lihat = [];

        foreach ($baris as $i => $b) {
            if (trim($b) === '') {
                continue;
            }
            $no = $i + 1;

            // Pemisah: tab (hasil paste Excel), lalu titik koma, lalu koma
            $kolom = str_contains($b, "\t") ? explode("\t", $b) : (str_contains($b, ';') ? explode(';', $b) : explode(',', $b, 2));
            $kolom = array_map(fn($k) => trim(trim($k), "\"' \t"), $kolom);
            $kolom = array_values(array_filter($kolom, fn($k) => $k !== ''));

            // Lewati baris judul (mis. "NIS  Nama Lengkap")
            if (count($kolom) >= 2 && preg_match('/^(nis|nisn)$/i', $kolom[0])) {
                continue;
            }

            if (count($kolom) < 2) {
                $rusak[] = ['baris' => $no, 'isi' => trim($b), 'alasan' => 'Harus ada NIS dan nama'];
                continue;
            }

            $nis = $kolom[0];
            $nama = preg_replace('/\s+/u', ' ', $kolom[1]);

            if (!preg_match('/^[A-Za-z0-9]{1,20}$/', $nis)) {
                $rusak[] = ['baris' => $no, 'isi' => trim($b), 'alasan' => 'NIS harus huruf/angka, maksimal 20 karakter'];
                continue;
            }
            if (mb_strlen($nama) > 100) {
                $rusak[] = ['baris' => $no, 'isi' => trim($b), 'alasan' => 'Nama lebih dari 100 karakter'];
                continue;
            }
            if (isset($lihat[$nis])) {
                $ganda[] = ['baris' => $no, 'nis' => $nis, 'nama' => $nama, 'alasan' => "NIS dobel di data (sama dengan baris {$lihat[$nis]})"];
                continue;
            }

            if (count($kandidat) >= self::MAKS_BARIS) {
                $terpotong = true;
                continue;
            }

            $lihat[$nis] = $no;
            $kandidat[] = ['baris' => $no, 'nis' => $nis, 'nama' => $nama];
        }

        // NIS yang sudah terdaftar (sebagai siswa atau sebagai nama pengguna akun apa pun)
        $sudah = [];
        foreach (array_chunk(array_column($kandidat, 'nis'), 500) as $potong) {
            foreach (DB::table('siswa')->whereIn('nisn', $potong)->pluck('nisn') as $n) {
                $sudah[(string) $n] = true;
            }
            foreach (DB::table('users')->whereIn('username', $potong)->pluck('username') as $n) {
                $sudah[(string) $n] = true;
            }
        }

        $valid = [];
        $ada = [];
        foreach ($kandidat as $k) {
            if (isset($sudah[$k['nis']])) {
                $ada[] = $k + ['alasan' => 'NIS sudah terdaftar'];
            } else {
                $valid[] = $k;
            }
        }

        return compact('valid', 'ada', 'ganda', 'rusak', 'terpotong');
    }
}
