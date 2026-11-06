<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use App\Support\Halaman;

class SiswaIzinController extends Controller
{
    public function index()
    {
        // Pengaman anti-bobol sesi siswa
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Silakan masuk dulu.']);
        }

        // Cari data siswa berdasarkan username (yang berisi NIS)
        $usernameLogin = session('username');
        $siswa = Siswa::where('nisn', $usernameLogin)->first();

        $riwayatIzin = collect();
        $rekapKehadiran = collect();

        if ($siswa) {
            // Riwayat semua pengajuan izin yang pernah dibuat siswa ini
            $riwayatIzin = LeaveRequest::where('student_id', $siswa->id_siswa)
                ->orderByDesc('created_at')
                ->paginate(10, ['*'], 'izin_page')
                ->withQueryString()
                ->fragment('tab-riwayat');

            // Rekap Kehadiran: histori per jam pelajaran (tanggal, mapel, status),
            // diambil dari tabel attendances yang otomatis terisi tiap guru mengisi jurnal.
            $rekapKehadiran = DB::table('attendances')
                ->join('journals', 'attendances.journal_id', '=', 'journals.id')
                ->join('jadwal_pelajaran', 'journals.schedule_id', '=', 'jadwal_pelajaran.id_jadwal')
                ->join('mata_pelajaran', 'jadwal_pelajaran.id_mapel', '=', 'mata_pelajaran.id_mapel')
                ->where('attendances.student_id', $siswa->id_siswa)
                ->select('journals.date', 'mata_pelajaran.nama_mapel', 'attendances.status')
                ->get();

            // Selain itu, tampilkan juga izin yang SUDAH di-Approve tapi belum
            // tersentuh jurnal guru manapun di tanggal itu — biar siswa langsung
            // lihat izinnya "kepakai" begitu di-Approve, tanpa harus nunggu ada
            // guru yang isi jurnal dulu.
            $tanggalSudahTercatat = $rekapKehadiran->pluck('date')->map(fn($d) => (string) $d)->unique();

            $izinBelumTersinkron = LeaveRequest::where('student_id', $siswa->id_siswa)
                ->where('status_approval', 'Approved')
                ->get()
                ->flatMap(function ($izin) use ($tanggalSudahTercatat) {
                    $rows = collect();
                    $periode = \Carbon\CarbonPeriod::create($izin->start_date, $izin->end_date);
                    foreach ($periode as $tgl) {
                        $tglStr = $tgl->toDateString();
                        if (!$tanggalSudahTercatat->contains($tglStr) && $tgl->toDateString() <= now()->toDateString()) {
                            $rows->push((object) [
                                'date' => $tglStr,
                                'nama_mapel' => 'Izin Harian (belum ada jurnal)',
                                'status' => $izin->jenis,
                            ]);
                        }
                    }
                    return $rows;
                });

            $rekapKehadiran = $rekapKehadiran->concat($izinBelumTersinkron)->sortByDesc('date')->values();
        }

        // Dua daftar di dua tab: masing-masing punya penomoran halaman sendiri (izin_page / hadir_page)
        $rekapKehadiran = Halaman::dari($rekapKehadiran, 15, 'hadir_page', 'tab-kehadiran');
        if (!$riwayatIzin instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $riwayatIzin = Halaman::dari($riwayatIzin, 10, 'izin_page', 'tab-riwayat');
        }

        return view('siswa.izin.index', compact('riwayatIzin', 'rekapKehadiran', 'siswa'));
    }

    public function store(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis.']);
        }

        // Cari data siswa berdasarkan username (yang berisi NIS)
        $usernameLogin = session('username');
        $siswa = Siswa::where('nisn', $usernameLogin)->first();

        if (!$siswa) {
            return redirect()->back()->withErrors(['error' => 'Data siswa dengan NIS itu tidak ditemukan.']);
        }

        $request->validate([
            'jenis' => 'required|in:Sakit,Izin',
            'start_date' => 'required|date|after_or_equal:' . now()->subDays(LeaveRequest::MAKS_HARI_TERLAMBAT)->toDateString(),
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'document' => 'required|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'start_date.after_or_equal' => 'Pengajuan izin maksimal ' . LeaveRequest::MAKS_HARI_TERLAMBAT . ' hari setelah tanggal tidak hadir. Untuk yang lebih lama, hubungi Wali Kelas / Waka Kesiswaan.',
        ]);

        $docPath = null;
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $file->extension();
            $docPath = $file->storeAs('izin', $filename, 'local');
        }

        LeaveRequest::create([
            'student_id' => $siswa->id_siswa,
            'applicant_id' => session('id_user'),
            'jenis' => $request->jenis,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'document_path' => $docPath,
            'status_approval' => 'Pending',
        ]);

        return redirect()->back()->with('success', 'Izin terkirim, menunggu verifikasi.');
    }
}
