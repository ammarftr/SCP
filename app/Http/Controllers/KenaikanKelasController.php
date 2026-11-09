<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kenaikan Kelas (khusus Waka Kurikulum).
 *
 * Aturan (sesuai pola nama kelas sekolah, siswa tidak diacak):
 *   X   RPL 1 -> XI  RPL 1
 *   XI  RPL 1 -> XII RPL 1
 *   XII RPL 1 -> Lulus (id_kelas dikosongkan, status_utama = Lulus, akun siswa dinonaktifkan)
 *
 * Halaman utama langsung berupa pratinjau. Waka bisa mencentang siswa yang TIDAK ikut diproses
 * (tinggal kelas, pindah, dsb.), lalu menekan satu tombol. Seluruh proses berjalan dalam satu
 * transaksi dan tercatat, sehingga proses terakhir bisa dibatalkan.
 */
class KenaikanKelasController extends Controller
{
    private const BERIKUTNYA = ['X' => 'XI', 'XI' => 'XII', 'XII' => null];

    /**
     * Buat tabel riwayat bila belum ada, supaya fitur langsung jalan di hosting
     * tanpa perlu menjalankan "php artisan migrate" (cPanel biasanya tanpa terminal).
     * Isinya sama dengan migrasi 2026_11_09_000000_buat_tabel_kenaikan_kelas.
     */
    private function siapkanTabel(): void
    {
        if (!Schema::hasTable('kenaikan_kelas')) {
            Schema::create('kenaikan_kelas', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('id_user')->nullable();
                $table->string('nama_pembuat', 100)->nullable();
                $table->unsignedInteger('jumlah_naik')->default(0);
                $table->unsignedInteger('jumlah_lulus')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('dibatalkan_pada')->nullable();
            });
        }

        if (!Schema::hasTable('kenaikan_kelas_detail')) {
            Schema::create('kenaikan_kelas_detail', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('id_kenaikan');
                $table->unsignedInteger('id_siswa');
                $table->string('kelas_asal', 20)->nullable();
                $table->string('kelas_tujuan', 20)->nullable();
                $table->string('status_asal', 10);
                $table->string('status_baru', 10);
                $table->unsignedInteger('id_user_dinonaktifkan')->nullable();

                $table->index('id_kenaikan');
                $table->foreign('id_kenaikan')->references('id')->on('kenaikan_kelas')->onDelete('cascade');
            });
        }
    }

    public function index()
    {
        $this->siapkanTabel();
        $rencana = $this->susunRencana();

        $riwayat = DB::table('kenaikan_kelas')->orderByDesc('id')->limit(10)->get();
        $idTerakhir = DB::table('kenaikan_kelas')->max('id');

        return view('waka.kenaikan_kelas', [
            'rencana'    => $rencana['kelas'],
            'tanda'      => $rencana['tanda'],
            'ringkas'    => $rencana['ringkas'],
            'riwayat'    => $riwayat,
            'idTerakhir' => $idTerakhir,
        ]);
    }

    public function proses(Request $request)
    {
        $request->validate([
            'tanda'        => 'required|string',
            'tidak_ikut'   => 'nullable|array',
            'tidak_ikut.*' => 'integer',
        ]);

        $this->siapkanTabel();
        $tidakIkut = array_flip(array_map('intval', (array) $request->input('tidak_ikut', [])));

        try {
            $hasil = DB::transaction(function () use ($request, $tidakIkut) {
                // Hitung ulang dari data terkini (tidak percaya data dari browser)
                $rencana = $this->susunRencana(true);

                if (!hash_equals($rencana['tanda'], (string) $request->input('tanda'))) {
                    return null; // data siswa berubah sejak halaman dibuka / sudah diproses
                }

                $logId = DB::table('kenaikan_kelas')->insertGetId([
                    'id_user'      => session('id_user'),
                    'nama_pembuat' => session('nama_lengkap'),
                ]);

                $jumlahNaik = 0;
                $jumlahLulus = 0;
                $detail = [];
                $kelasAda = DB::table('kelas')->pluck('id_kelas')->flip();

                foreach ($rencana['kelas'] as $k) {
                    if ($k['jenis'] === 'tidak_dikenali') {
                        continue;
                    }

                    $ikut = array_values(array_filter($k['siswa'], fn($s) => !isset($tidakIkut[$s->id_siswa])));
                    if (!$ikut) {
                        continue;
                    }
                    $ids = array_map(fn($s) => $s->id_siswa, $ikut);

                    if ($k['jenis'] === 'naik') {
                        if (!isset($kelasAda[$k['tujuan']])) {
                            DB::table('kelas')->insert([
                                'id_kelas'   => $k['tujuan'],
                                'tingkat'    => $k['tingkat_tujuan'],
                                'id_jurusan' => $k['id_jurusan'],
                            ]);
                            $kelasAda[$k['tujuan']] = true;
                        }

                        DB::table('siswa')->whereIn('id_siswa', $ids)->update(['id_kelas' => $k['tujuan']]);
                        foreach ($ikut as $s) {
                            $detail[] = [
                                'id_kenaikan' => $logId, 'id_siswa' => $s->id_siswa,
                                'kelas_asal' => $k['asal'], 'kelas_tujuan' => $k['tujuan'],
                                'status_asal' => 'Aktif', 'status_baru' => 'Aktif',
                                'id_user_dinonaktifkan' => null,
                            ];
                        }
                        $jumlahNaik += count($ikut);
                    } else { // lulus
                        // Akun siswa (username = NISN) yang masih aktif ikut dinonaktifkan
                        $nisn = array_map(fn($s) => $s->nisn, $ikut);
                        $akun = DB::table('users')->where('id_role', 5)->where('is_aktif', 1)
                            ->whereIn('username', $nisn)->pluck('id_user', 'username');

                        DB::table('siswa')->whereIn('id_siswa', $ids)->update(['id_kelas' => null, 'status_utama' => 'Lulus']);
                        if ($akun->isNotEmpty()) {
                            DB::table('users')->whereIn('id_user', $akun->values()->all())->update(['is_aktif' => 0]);
                        }
                        foreach ($ikut as $s) {
                            $detail[] = [
                                'id_kenaikan' => $logId, 'id_siswa' => $s->id_siswa,
                                'kelas_asal' => $k['asal'], 'kelas_tujuan' => null,
                                'status_asal' => 'Aktif', 'status_baru' => 'Lulus',
                                'id_user_dinonaktifkan' => $akun[$s->nisn] ?? null,
                            ];
                        }
                        $jumlahLulus += count($ikut);
                    }
                }

                foreach (array_chunk($detail, 500) as $potong) {
                    DB::table('kenaikan_kelas_detail')->insert($potong);
                }

                if ($jumlahNaik + $jumlahLulus === 0) {
                    DB::table('kenaikan_kelas')->where('id', $logId)->delete();
                    return ['kosong' => true];
                }

                DB::table('kenaikan_kelas')->where('id', $logId)->update([
                    'jumlah_naik' => $jumlahNaik, 'jumlah_lulus' => $jumlahLulus,
                ]);

                return ['naik' => $jumlahNaik, 'lulus' => $jumlahLulus];
            });
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('waka.kenaikan.index')
                ->withErrors(['error' => 'Proses gagal dan tidak ada data yang berubah. Coba lagi.']);
        }

        if ($hasil === null) {
            return redirect()->route('waka.kenaikan.index')
                ->withErrors(['error' => 'Data siswa berubah sejak halaman dibuka (atau proses sudah dijalankan). Periksa pratinjau terbaru lalu coba lagi.']);
        }
        if (!empty($hasil['kosong'])) {
            return redirect()->route('waka.kenaikan.index')
                ->withErrors(['error' => 'Tidak ada siswa yang diproses.']);
        }

        return redirect()->route('waka.kenaikan.index')
            ->with('success', "Kenaikan kelas selesai: {$hasil['naik']} siswa naik kelas, {$hasil['lulus']} siswa lulus.");
    }

    public function batalkan($id)
    {
        $this->siapkanTabel();
        $pesan = DB::transaction(function () use ($id) {
            $log = DB::table('kenaikan_kelas')->where('id', $id)->lockForUpdate()->first();
            if (!$log || $log->dibatalkan_pada) {
                return ['error', 'Proses ini tidak ditemukan atau sudah dibatalkan.'];
            }
            // Hanya proses paling akhir yang boleh dibatalkan, supaya tidak bertabrakan dengan proses sesudahnya.
            if ((int) $id !== (int) DB::table('kenaikan_kelas')->max('id')) {
                return ['error', 'Hanya proses kenaikan kelas yang terakhir yang bisa dibatalkan.'];
            }

            $dikembalikan = 0;
            $dilewati = 0;

            foreach (DB::table('kenaikan_kelas_detail')->where('id_kenaikan', $id)->orderBy('id')->cursor() as $d) {
                $siswa = DB::table('siswa')->where('id_siswa', $d->id_siswa)->lockForUpdate()->first();
                // Jangan menimpa siswa yang datanya sudah diubah manual sesudah proses
                if (!$siswa || $siswa->id_kelas !== $d->kelas_tujuan || $siswa->status_utama !== $d->status_baru) {
                    $dilewati++;
                    continue;
                }
                DB::table('siswa')->where('id_siswa', $d->id_siswa)->update([
                    'id_kelas' => $d->kelas_asal, 'status_utama' => $d->status_asal,
                ]);
                if ($d->id_user_dinonaktifkan) {
                    DB::table('users')->where('id_user', $d->id_user_dinonaktifkan)->update(['is_aktif' => 1]);
                }
                $dikembalikan++;
            }

            DB::table('kenaikan_kelas')->where('id', $id)->update(['dibatalkan_pada' => now()]);

            $teks = "Proses dibatalkan: {$dikembalikan} siswa dikembalikan ke kelas semula.";
            if ($dilewati) {
                $teks .= " {$dilewati} siswa dilewati karena datanya sudah diubah manual.";
            }
            return ['success', $teks];
        });

        return $pesan[0] === 'success'
            ? redirect()->route('waka.kenaikan.index')->with('success', $pesan[1])
            : redirect()->route('waka.kenaikan.index')->withErrors(['error' => $pesan[1]]);
    }

    /**
     * Susun rencana per kelas dari siswa berstatus Aktif.
     *
     * @return array{kelas: array<int,array>, tanda: string, ringkas: array}
     */
    private function susunRencana(bool $kunci = false): array
    {
        $q = DB::table('siswa')
            ->join('kelas', 'kelas.id_kelas', '=', 'siswa.id_kelas')
            ->where('siswa.status_utama', 'Aktif')
            ->orderBy('siswa.id_kelas')->orderBy('siswa.nama_siswa')
            ->select('siswa.id_siswa', 'siswa.nisn', 'siswa.nama_siswa', 'siswa.id_kelas', 'kelas.tingkat', 'kelas.id_jurusan');
        if ($kunci) {
            $q->lockForUpdate();
        }
        $semua = $q->get();

        $kelasAda = DB::table('kelas')->pluck('id_kelas')->flip();

        $kelas = [];
        foreach ($semua->groupBy('id_kelas') as $idKelas => $siswa) {
            $tingkat = $siswa->first()->tingkat;
            $kelas[] = $this->rencanaKelas((string) $idKelas, (string) $tingkat, $siswa->first()->id_jurusan, $siswa->all(), $kelasAda);
        }

        $tanda = md5($semua->map(fn($s) => $s->id_siswa . ':' . $s->id_kelas)->implode('|'));

        $ringkas = ['naik' => 0, 'lulus' => 0, 'tidak_dikenali' => 0, 'buat' => 0];
        foreach ($kelas as $k) {
            $n = count($k['siswa']);
            if ($k['jenis'] === 'naik') {
                $ringkas['naik'] += $n;
                if (!$k['tujuan_ada']) {
                    $ringkas['buat']++;
                }
            } elseif ($k['jenis'] === 'lulus') {
                $ringkas['lulus'] += $n;
            } else {
                $ringkas['tidak_dikenali'] += $n;
            }
        }

        return ['kelas' => $kelas, 'tanda' => $tanda, 'ringkas' => $ringkas];
    }

    private function rencanaKelas(string $asal, string $tingkat, ?string $idJurusan, array $siswa, $kelasAda): array
    {
        $dasar = [
            'asal' => $asal, 'tingkat' => $tingkat, 'id_jurusan' => $idJurusan, 'siswa' => $siswa,
            'tujuan' => null, 'tingkat_tujuan' => null, 'tujuan_ada' => true,
        ];

        // Nama kelas harus diawali tingkatnya + spasi, mis. "XI RPL 1"
        if (!array_key_exists($tingkat, self::BERIKUTNYA) || !preg_match('/^' . preg_quote($tingkat, '/') . '\s+(.+)$/u', $asal, $m)) {
            return $dasar + ['jenis' => 'tidak_dikenali'];
        }

        $berikut = self::BERIKUTNYA[$tingkat];
        if ($berikut === null) {
            return $dasar + ['jenis' => 'lulus'];
        }

        $tujuan = $berikut . ' ' . $m[1];

        return array_merge($dasar, [
            'jenis'          => 'naik',
            'tujuan'         => $tujuan,
            'tingkat_tujuan' => $berikut,
            'tujuan_ada'     => isset($kelasAda[$tujuan]),
        ]);
    }
}
