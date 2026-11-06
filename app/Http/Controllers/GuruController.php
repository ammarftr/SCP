<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JadwalPelajaran;
use App\Models\Guru;

class GuruController extends Controller
{
    private $hariMap = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', // Sabtu & Minggu libur: tidak dipetakan (hasil null = tidak ada jadwal)
    ];

    public function dashboard()
    {
        $guru = $this->currentGuru();
        $config = DB::table('system_configurations')->first();
        $activeCycle = $config->active_cycle ?? 'Blok 1';
        $lockB1 = (bool) ($config->is_locked_blok_1 ?? false);
        $lockB2 = (bool) ($config->is_locked_blok_2 ?? false);

        $alerts = collect();
        $jadwalHariIni = collect();
        $jadwalBesok = collect();

        if ($guru) {
            $alerts = $this->getUnfilledJournalAlerts($activeCycle, $guru->id_guru);

            $hariIni = $this->hariMap[now()->format('l')] ?? null;
            $besok = now()->copy()->addDay();
            $hariBesok = $this->hariMap[$besok->format('l')] ?? null;

            $jadwalHariIni = JadwalPelajaran::query()->with(['kelas', 'mataPelajaran', 'ruangan'])
                ->where('id_guru', $guru->id_guru)
                ->where('hari', $hariIni)
                ->where('cycle_type', $activeCycle)
                ->orderBy('jam_mulai')
                ->get();

            $jadwalBesok = JadwalPelajaran::query()->with(['kelas', 'mataPelajaran', 'ruangan'])
                ->where('id_guru', $guru->id_guru)
                ->where('hari', $hariBesok)
                ->where('cycle_type', $activeCycle)
                ->orderBy('jam_mulai')
                ->get();
        }

        // Guru yang merangkap wali kelas: jumlah izin siswa kelasnya yang masih menunggu persetujuan
        $kelasWali = $this->kelasPerwalian();
        $izinMenunggu = null;
        if ($kelasWali) {
            $izinMenunggu = DB::table('leave_requests')
                ->join('siswa', 'leave_requests.student_id', '=', 'siswa.id_siswa')
                ->where('siswa.id_kelas', $kelasWali)
                ->where('leave_requests.status_approval', 'Pending')
                ->count();
        }

        // Guru BK: izin yang masih menunggu, hanya dari kelas yang diampu
        $sebagaiBk = (int) session('id_role') === 7;
        $kelasBk = [];
        if ($sebagaiBk) {
            $kelasBk = $this->kelasBk();
            $izinMenunggu = DB::table('leave_requests')
                ->join('siswa', 'leave_requests.student_id', '=', 'siswa.id_siswa')
                ->whereIn('siswa.id_kelas', $kelasBk)
                ->where('leave_requests.status_approval', 'Pending')
                ->count();
        }

        return view('guru.dashboard', compact('alerts', 'jadwalHariIni', 'jadwalBesok', 'activeCycle', 'guru', 'lockB1', 'lockB2', 'kelasWali', 'izinMenunggu', 'sebagaiBk', 'kelasBk'));
    }

    /**
     * Dashboard Alert Reminder khusus untuk guru yang login sendiri.
     * Guru A tidak akan melihat alert milik Guru B (jadwal hanya difilter id_guru miliknya).
     */
    private function getUnfilledJournalAlerts(?string $activeCycle, int $idGuru)
    {
        $hariIni = $this->hariMap[now()->format('l')] ?? null;
        $batasWaktu = now()->subMinutes(30)->format('H:i:s');

        if (!$hariIni) {
            return collect();
        }

        return JadwalPelajaran::query()->with(['kelas', 'mataPelajaran'])
            ->where('id_guru', $idGuru)
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
