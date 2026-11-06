<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JadwalPelajaran;

class WakaController extends Controller
{
    private $hariMap = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', // Sabtu & Minggu libur: tidak dipetakan (hasil null = tidak ada jadwal)
    ];

    private function getConfig()
    {
        $config = DB::table('system_configurations')->first();
        if (!$config) {
            DB::table('system_configurations')->insert([
                'active_cycle' => 'Blok 1',
                'is_schedule_locked' => false,
                'is_locked_blok_1' => false,
                'is_locked_blok_2' => false,
                'updated_at' => now(),
            ]);
            $config = DB::table('system_configurations')->first();
        }
        return $config;
    }

    public function dashboard()
    {
        $config = $this->getConfig();
        $alerts = $this->getUnfilledJournalAlerts($config->active_cycle);

        // Statistik ringkas untuk dashboard (konten khas dashboard, bukan sekadar link fitur)
        $stats = [
            'guru'   => DB::table('guru')->count(),
            'siswa'  => DB::table('siswa')->count(),
            'kelas'  => DB::table('kelas')->count(),
            'jadwal' => JadwalPelajaran::query()->count(),
        ];

        $jurnalHariIni = DB::table('journals')->whereDate('date', now()->toDateString())->count();

        return view('waka.dashboard', compact('config', 'alerts', 'stats', 'jurnalHariIni'));
    }

    /**
     * Manajemen Jadwal (Waka): daftar seluruh jadwal dengan filter & paginasi di sisi server,
     * supaya tetap ringan walau jadwal sudah ribuan baris (puluhan kelas x 2 blok).
     */
    public function jadwal(Request $request)
    {
        $config = $this->getConfig();
        $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        // Bawaan: tampilkan blok yang sedang aktif. "semua" = kedua blok sekaligus.
        $blok = $request->query('blok', $config->active_cycle);
        if (!in_array($blok, ['Blok 1', 'Blok 2', 'semua'], true)) {
            $blok = $config->active_cycle;
        }

        $perPage = in_array((int) $request->query('per_page'), [25, 50, 100], true)
            ? (int) $request->query('per_page') : 25;

        $filter = [
            'hari'    => in_array($request->query('hari'), $hariList, true) ? $request->query('hari') : null,
            'kelas'   => $request->query('kelas') ?: null,
            'guru'    => $request->query('guru') ?: null,
            'ruangan' => $request->query('ruangan') ?: null,
            'q'       => trim((string) $request->query('q', '')),
        ];

        // Filter selain blok (dipakai juga untuk angka di tab Blok 1 / Blok 2)
        $terapkan = function ($query) use ($filter) {
            return $query
                ->when($filter['hari'], fn($q, $v) => $q->where('hari', $v))
                ->when($filter['kelas'], fn($q, $v) => $q->where('id_kelas', $v))
                ->when($filter['guru'], fn($q, $v) => $q->where('id_guru', $v))
                ->when($filter['ruangan'], fn($q, $v) => $q->where('id_ruangan_default', $v))
                ->when($filter['q'] !== '', function ($q) use ($filter) {
                    $like = '%' . addcslashes($filter['q'], '%_\\') . '%';
                    $q->where(function ($w) use ($like) {
                        $w->where('id_kelas', 'like', $like)
                          ->orWhereHas('mataPelajaran', fn($m) => $m->where('nama_mapel', 'like', $like))
                          ->orWhereHas('guru', fn($g) => $g->where('nama_guru', 'like', $like));
                    });
                });
        };

        $jumlahPerBlok = $terapkan(JadwalPelajaran::query())
            ->select('cycle_type', DB::raw('count(*) as total'))
            ->groupBy('cycle_type')->pluck('total', 'cycle_type');

        $dasar = $terapkan(JadwalPelajaran::query())
            ->when($blok !== 'semua', fn($q) => $q->where('cycle_type', $blok));

        $ringkas = (clone $dasar)->selectRaw(
            'count(*) as total, count(distinct id_guru) as guru, count(distinct id_kelas) as kelas, count(distinct id_ruangan_default) as ruangan'
        )->first();

        $jadwals = $dasar->with(['kelas', 'mataPelajaran', 'guru', 'ruangan'])
            ->when($blok === 'semua', fn($q) => $q->orderBy('cycle_type'))
            ->orderByRaw("FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat')")
            ->orderBy('jam_mulai')
            ->orderBy('id_kelas')
            ->paginate($perPage)
            ->withQueryString();

        $kelasList   = DB::table('kelas')->orderBy('id_kelas')->get(['id_kelas']);
        $guruList    = DB::table('guru')->orderBy('nama_guru')->get(['id_guru', 'nama_guru']);
        $ruanganList = DB::table('ruangan')->orderBy('nama_ruangan')->get(['id_ruangan', 'nama_ruangan']);

        $kunci = [
            'Blok 1' => (bool) $config->is_locked_blok_1,
            'Blok 2' => (bool) $config->is_locked_blok_2,
        ];

        return view('waka.jadwal', compact(
            'config', 'jadwals', 'blok', 'filter', 'perPage', 'hariList', 'jumlahPerBlok', 'ringkas',
            'kelasList', 'guruList', 'ruanganList', 'kunci'
        ));
    }

    /**
     * Ubah blok aktif dan/atau kunci jadwal per blok langsung dari Beranda.
     * Hanya field yang dikirim yang diubah (satu klik = satu perubahan).
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'active_cycle'   => 'sometimes|in:Blok 1,Blok 2',
            'is_locked_blok_1' => 'sometimes|boolean',
            'is_locked_blok_2' => 'sometimes|boolean',
        ]);

        $config = $this->getConfig();
        $data = $request->only(['active_cycle', 'is_locked_blok_1', 'is_locked_blok_2']);

        if (empty($data)) {
            return back();
        }

        // Mengaktifkan blok otomatis mengunci blok itu (jadwalnya sudah berjalan) dan membuka
        // blok lainnya. Kunci tetap bisa diubah manual lewat tombol kunci (mis. membuka keduanya).
        if (isset($data['active_cycle'])) {
            $data['is_locked_blok_1'] = $data['active_cycle'] === 'Blok 1' ? 1 : 0;
            $data['is_locked_blok_2'] = $data['active_cycle'] === 'Blok 2' ? 1 : 0;
        }

        $lock1 = (bool) ($data['is_locked_blok_1'] ?? $config->is_locked_blok_1);
        $lock2 = (bool) ($data['is_locked_blok_2'] ?? $config->is_locked_blok_2);

        DB::table('system_configurations')->where('id', $config->id)->update($data + [
            // kolom lama: dianggap terkunci hanya kalau kedua blok terkunci
            'is_schedule_locked' => ($lock1 && $lock2) ? 1 : 0,
            'updated_by' => session('id_user'),
            'updated_at' => now(),
        ]);

        if (isset($data['active_cycle'])) {
            $lain = $data['active_cycle'] === 'Blok 1' ? 'Blok 2' : 'Blok 1';
            $msg = 'Blok aktif sekarang: ' . $data['active_cycle'] . '. Jadwal ' . $data['active_cycle'] . ' dikunci, jadwal ' . $lain . ' dibuka.';
        } else {
            $nm  = isset($data['is_locked_blok_1']) ? 'Blok 1' : 'Blok 2';
            $val = $data['is_locked_blok_1'] ?? $data['is_locked_blok_2'];
            $msg = 'Jadwal ' . $nm . ' ' . ($val ? 'dikunci.' : 'dibuka.');
        }

        return back()->with('success', $msg);
    }

    /**
     * Dashboard Alert Reminder (global — semua guru, semua mapel).
     * Jadwal hari ini yang jam_selesai-nya sudah lewat >30 menit
     * namun belum ada jurnal untuk tanggal hari ini.
     * Dihitung real-time saat halaman dimuat (tanpa Cron Job), mengikuti waktu & hari server saat ini.
     */
    private function getUnfilledJournalAlerts(?string $activeCycle)
    {
        $hariIni = $this->hariMap[now()->format('l')] ?? null;
        $batasWaktu = now()->subMinutes(30)->format('H:i:s');

        if (!$hariIni) {
            return collect();
        }

        return JadwalPelajaran::query()->with(['guru', 'kelas', 'mataPelajaran'])
            ->where('hari', $hariIni)
            ->where('cycle_type', $activeCycle ?? 'Blok 1')
            ->whereNotNull('jam_selesai')
            ->where('jam_selesai', '<=', $batasWaktu)
            ->get()
            ->filter(function ($jadwal) {
                return !DB::table('journals')
                    ->where('schedule_id', $jadwal->id_jadwal)
                    ->whereDate('date', now()->toDateString())
                    ->exists();
            })->values();
    }
}
