<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;
use App\Support\Halaman;

class KesiswaanController extends Controller
{
    /**
     * Daftar pengajuan izin. Default: hanya yang diajukan HARI INI.
     * ?riwayat=1 -> semua histori. ?q= -> pencarian nama/NIS/alasan.
     */
    public function index(Request $request)
    {
        $tampilkanRiwayat = $request->boolean('riwayat');
        $keyword = $request->query('q');

        $kelasWali = $this->kelasPerwalian();

        $query = LeaveRequest::with('student');

        // Wali Kelas hanya melihat izin siswa kelas perwaliannya; Guru BK hanya kelas yang diampu
        $batas = $this->batasKelas();
        if ($batas !== null) {
            $query->whereHas('student', fn($q) => $q->whereIn('id_kelas', $batas));
        }

        if (!$tampilkanRiwayat) {
            $query->whereDate('created_at', now()->toDateString());
        }

        if ($keyword) {
            // Dibungkus satu grup agar OR tidak melewati batas kelas di atas
            $query->where(function ($w) use ($keyword) {
                $w->whereHas('student', function ($q) use ($keyword) {
                    $q->where('nama_siswa', 'like', "%{$keyword}%")
                      ->orWhere('nisn', 'like', "%{$keyword}%");
                })->orWhere('reason', 'like', "%{$keyword}%");
            });
        }

        $pendingLeaves = $query->orderByDesc('created_at')->paginate(15)->withQueryString();
        $kelasBk = (int) session('id_role') === 7 ? $batas : null;

        return view('kesiswaan.izin.index', compact('pendingLeaves', 'tampilkanRiwayat', 'keyword', 'kelasWali', 'kelasBk'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status_approval' => 'required|in:Approved,Rejected,Pending',
            'alasan_penolakan' => 'required_if:status_approval,Rejected|nullable|string|max:500',
        ], [
            'alasan_penolakan.required_if' => 'Alasan penolakan wajib diisi.',
            'alasan_penolakan.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        $leave = LeaveRequest::with('student')->findOrFail($id);

        // Wali Kelas / Guru BK tidak boleh memproses izin siswa di luar kelasnya
        $batas = $this->batasKelas();
        abort_if(
            $batas !== null && !in_array((string) ($leave->student->id_kelas ?? ''), $batas, true),
            403,
            'Siswa ini bukan dari kelas yang Anda ampu.'
        );

        DB::transaction(function () use ($leave, $request) {
            $leave->update([
                'status_approval' => $request->status_approval,
                'verified_by' => session('id_user'),
                // Alasan hanya disimpan untuk penolakan; status lain mengosongkannya.
                'alasan_penolakan' => $request->status_approval === 'Rejected' ? trim($request->alasan_penolakan) : null,
            ]);

            // Selalu kembalikan dulu koreksi lama dari izin ini (idempotent), lalu
            // terapkan lagi hanya kalau statusnya Approved.
            $this->kembalikanKoreksiPresensi($leave);
            if ($request->status_approval === 'Approved') {
                $this->koreksiAlfaDariIzin($leave);
            }
        });

        return redirect()->back()->with('success', 'Status izin diperbarui.');
    }

    /**
     * Izin yang di-Approve menimpa presensi berstatus ALFA pada tanggal dalam rentang
     * izin (semua jam pelajaran hari itu) jadi Sakit/Izin. Status Hadir/Sakit/Izin
     * dari guru tidak disentuh. Status asli disimpan untuk audit & pengembalian.
     */
    private function koreksiAlfaDariIzin(LeaveRequest $leave): void
    {
        DB::table('attendances')
            ->join('journals', 'attendances.journal_id', '=', 'journals.id')
            ->where('attendances.student_id', $leave->student_id)
            ->where('attendances.status', 'Alfa')
            ->whereBetween('journals.date', [
                \Carbon\Carbon::parse($leave->start_date)->toDateString(),
                \Carbon\Carbon::parse($leave->end_date)->toDateString(),
            ])
            ->update([
                'attendances.status' => $leave->jenis,
                'attendances.status_asal' => 'Alfa',
                'attendances.leave_request_id' => $leave->id,
                'attendances.dikoreksi_oleh' => session('id_user'),
                'attendances.dikoreksi_pada' => now(),
            ]);
    }

    /** Batalkan koreksi: presensi yang pernah ditimpa izin ini kembali ke status asalnya. */
    private function kembalikanKoreksiPresensi(LeaveRequest $leave): void
    {
        DB::table('attendances')
            ->where('leave_request_id', $leave->id)
            ->update([
                'status' => DB::raw('status_asal'),
                'status_asal' => null,
                'leave_request_id' => null,
                'dikoreksi_oleh' => null,
                'dikoreksi_pada' => null,
            ]);
    }

    /**
     * Rekap Absensi — fokus ke siswa yang PERLU PERHATIAN (Sakit/Izin/Alfa).
     * Status "Hadir" disembunyikan secara default. Dikelompokkan per Kelas,
     * dan per Kelas dikelompokkan lagi per Siswa (kalau 1 siswa punya beberapa
     * status beda di beberapa mapel hari itu, digabung jadi badge dalam 1 baris
     * — biar gak keulang-ulang namanya dan tetap rapi walau puluhan kelas).
     */
    public function rekapAbsensi(Request $request)
    {
        $tanggal = $request->query('tanggal', now()->toDateString());
        $keyword = $request->query('q');
        $kelasFilter = $request->query('kelas');
        $tampilkanSemua = $request->boolean('semua');

        // Wali Kelas: kelas dikunci ke kelas perwalian, apa pun isi ?kelas= di URL
        $kelasWali = $this->kelasPerwalian();
        if ($kelasWali !== null) {
            $kelasFilter = $kelasWali !== '' ? $kelasWali : '__tidak_ada__';
            $daftarKelas = $kelasWali !== '' ? collect([$kelasWali]) : collect();
        } else {
            // Guru BK: hanya kelas yang diampu (Waka/lainnya: semua kelas)
            $batas = $this->batasKelas();
            $daftarKelas = $batas !== null
                ? collect($batas)->sort()->values()
                : DB::table('kelas')->orderBy('id_kelas')->pluck('id_kelas');

            // ?kelas= di luar kelas yang diampu diabaikan
            if ($batas !== null && $kelasFilter && !in_array((string) $kelasFilter, $batas, true)) {
                $kelasFilter = null;
            }
        }
        $batas = $this->batasKelas();

        $baseQuery = DB::table('attendances')
            ->join('journals', 'attendances.journal_id', '=', 'journals.id')
            ->join('siswa', 'attendances.student_id', '=', 'siswa.id_siswa')
            ->join('jadwal_pelajaran', 'journals.schedule_id', '=', 'jadwal_pelajaran.id_jadwal')
            ->join('mata_pelajaran', 'jadwal_pelajaran.id_mapel', '=', 'mata_pelajaran.id_mapel')
            ->whereDate('journals.date', $tanggal);

        if ($kelasFilter) {
            $baseQuery->where('siswa.id_kelas', $kelasFilter);
        } elseif ($batas !== null) {
            $baseQuery->whereIn('siswa.id_kelas', $batas);
        }

        // Ringkasan angka (dihitung dari SEMUA data hari itu & kelas terpilih, tidak kena filter status/search)
        $ringkasan = (clone $baseQuery)
            ->select('attendances.status', DB::raw('count(*) as total'))
            ->groupBy('attendances.status')
            ->pluck('total', 'status');

        $query = (clone $baseQuery)->select(
            'siswa.id_siswa', 'siswa.nama_siswa', 'siswa.nisn', 'siswa.id_kelas',
            'mata_pelajaran.nama_mapel', 'attendances.status', 'attendances.leave_request_id'
        );

        if (!$tampilkanSemua) {
            $query->where('attendances.status', '!=', 'Hadir');
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('siswa.nama_siswa', 'like', "%{$keyword}%")
                  ->orWhere('siswa.nisn', 'like', "%{$keyword}%");
            });
        }

        $rows = $query->orderBy('siswa.id_kelas')->orderBy('siswa.nama_siswa')->get();

        // Selain dari jurnal yang sudah disinkron, tampilkan juga siswa yang izinnya
        // SUDAH di-Approve untuk tanggal ini tapi BELUM ada jurnal guru yang menyentuhnya
        // hari itu (jadi belum otomatis tercatat di tabel attendances). Wali Kelas/BK jadi
        // langsung tahu begitu izin disetujui, tanpa harus nunggu ada guru isi jurnal dulu.
        $siswaSudahTercatat = $rows->pluck('id_siswa')->unique();

        $izinBelumTersinkron = LeaveRequest::with('student')
            ->where('status_approval', 'Approved')
            ->whereDate('start_date', '<=', $tanggal)
            ->whereDate('end_date', '>=', $tanggal)
            ->get()
            ->filter(fn($izin) => $izin->student && !$siswaSudahTercatat->contains($izin->student->id_siswa));

        if ($kelasFilter) {
            $izinBelumTersinkron = $izinBelumTersinkron->filter(fn($izin) => $izin->student->id_kelas == $kelasFilter);
        } elseif ($batas !== null) {
            $izinBelumTersinkron = $izinBelumTersinkron->filter(fn($izin) => in_array((string) $izin->student->id_kelas, $batas, true));
        }
        if ($keyword) {
            $izinBelumTersinkron = $izinBelumTersinkron->filter(function ($izin) use ($keyword) {
                return str_contains(strtolower($izin->student->nama_siswa), strtolower($keyword))
                    || str_contains(strtolower($izin->student->nisn), strtolower($keyword));
            });
        }

        $rowsTambahan = $izinBelumTersinkron->map(function ($izin) {
            return (object) [
                'id_siswa' => $izin->student->id_siswa,
                'nama_siswa' => $izin->student->nama_siswa,
                'nisn' => $izin->student->nisn,
                'id_kelas' => $izin->student->id_kelas,
                'nama_mapel' => 'Izin Harian (belum ada jurnal)',
                'status' => $izin->jenis,
                'leave_request_id' => $izin->id,
            ];
        });

        // Tambahkan juga ke ringkasan angka biar totalnya akurat
        foreach ($rowsTambahan->groupBy('status') as $status => $group) {
            $ringkasan[$status] = ($ringkasan[$status] ?? 0) + $group->count();
        }

        $rows = $rows->concat($rowsTambahan);

        // Kalau lagi mode "sembunyikan Hadir", baris tambahan ini juga selalu Sakit/Izin jadi otomatis lolos filter itu.

        // Kelompokkan: per Kelas -> per Siswa -> list badge {mapel, status}
        $rekapPerKelas = $rows->groupBy('id_kelas')->map(function ($siswaRows) {
            return $siswaRows->groupBy('id_siswa')->map(function ($detailRows) {
                $first = $detailRows->first();
                return (object) [
                    'nama_siswa' => $first->nama_siswa,
                    'nisn' => $first->nisn,
                    'kelas' => $first->id_kelas,
                    // dari_izin: status ini hasil koreksi izin yang disetujui (bukan diinput guru)
                    'detail' => $detailRows->map(fn($d) => [
                        'mapel' => $d->nama_mapel,
                        'status' => $d->status,
                        'dari_izin' => !empty($d->leave_request_id),
                    ]),
                ];
            })->values();
        });

        // Paginasi per SISWA (urutan kelas -> nama tetap), lalu dikelompokkan lagi per kelas
        // hanya untuk siswa di halaman ini. Jumlah siswa per kelas tetap dihitung dari seluruh data.
        $semuaSiswa = $rekapPerKelas->flatten(1)->values();
        $jumlahPerKelas = $semuaSiswa->countBy('kelas');
        $halamanSiswa = Halaman::dari($semuaSiswa, 20);
        $rekapPerKelas = $halamanSiswa->getCollection()->groupBy('kelas');

        return view('kesiswaan.rekap_absensi', compact(
            'rekapPerKelas', 'halamanSiswa', 'jumlahPerKelas', 'tanggal', 'keyword', 'tampilkanSemua', 'ringkasan', 'daftarKelas', 'kelasFilter', 'kelasWali'
        ));
    }

    /**
     * Laporan Izin Harian — daftar PENGAJUAN izin/sakit yang BERLAKU pada tanggal terpilih
     * (start_date <= tanggal <= end_date), lengkap dengan status verifikasinya.
     *
     * Beda dengan Rekap Absensi: sumber datanya pengajuan (leave_requests), bukan presensi
     * per mata pelajaran (attendances). Kolom "Presensi" hanya memperlihatkan apakah izin yang
     * disetujui sudah ikut mengoreksi presensi guru pada tanggal itu.
     *
     * ?tanggal=Y-m-d  ?kelas=  ?jenis=Sakit|Izin  ?status=Approved|Pending|Rejected  ?q=
     */
    public function laporanIzinHarian(Request $request)
    {
        $tanggal = $this->tanggalAman($request->query('tanggal'));
        $keyword = trim((string) $request->query('q', ''));
        $jenis = in_array($request->query('jenis'), ['Sakit', 'Izin'], true) ? $request->query('jenis') : null;
        $status = in_array($request->query('status'), ['Approved', 'Pending', 'Rejected'], true) ? $request->query('status') : null;
        $kelasFilter = $request->query('kelas');

        // Wali Kelas: kelas dikunci ke kelas perwalian, apa pun isi ?kelas= di URL
        $kelasWali = $this->kelasPerwalian();
        if ($kelasWali !== null) {
            $kelasFilter = $kelasWali !== '' ? $kelasWali : '__tidak_ada__';
            $daftarKelas = $kelasWali !== '' ? collect([$kelasWali]) : collect();
        } else {
            // Guru BK: hanya kelas yang diampu (Waka/lainnya: semua kelas)
            $batas = $this->batasKelas();
            $daftarKelas = $batas !== null
                ? collect($batas)->sort()->values()
                : DB::table('kelas')->orderBy('id_kelas')->pluck('id_kelas');

            // ?kelas= di luar kelas yang diampu diabaikan
            if ($batas !== null && $kelasFilter && !in_array((string) $kelasFilter, $batas, true)) {
                $kelasFilter = null;
            }
        }
        $batas = $this->batasKelas();

        // Dasar: izin yang berlaku pada tanggal itu (+ batas kelas)
        $base = LeaveRequest::query()
            ->whereDate('start_date', '<=', $tanggal)
            ->whereDate('end_date', '>=', $tanggal);

        if ($kelasFilter) {
            $base->whereHas('student', fn($q) => $q->where('id_kelas', $kelasFilter));
        } elseif ($batas !== null) {
            $base->whereHas('student', fn($q) => $q->whereIn('id_kelas', $batas));
        }

        // Ringkasan angka: tidak kena filter jenis/status/pencarian
        $semuaHariIni = (clone $base)->get(['id', 'jenis', 'status_approval']);
        $ringkasan = [
            'Sakit'    => $semuaHariIni->where('status_approval', 'Approved')->where('jenis', 'Sakit')->count(),
            'Izin'     => $semuaHariIni->where('status_approval', 'Approved')->where('jenis', 'Izin')->count(),
            'Pending'  => $semuaHariIni->where('status_approval', 'Pending')->count(),
            'Rejected' => $semuaHariIni->where('status_approval', 'Rejected')->count(),
        ];

        $query = (clone $base)->with('student');
        if ($jenis) {
            $query->where('jenis', $jenis);
        }
        if ($status) {
            $query->where('status_approval', $status);
        }
        if ($keyword !== '') {
            $query->where(function ($w) use ($keyword) {
                $w->whereHas('student', function ($q) use ($keyword) {
                    $q->where('nama_siswa', 'like', "%{$keyword}%")
                      ->orWhere('nisn', 'like', "%{$keyword}%");
                })->orWhere('reason', 'like', "%{$keyword}%");
            });
        }

        $izinSemua = $query->get()
            ->sortBy(fn($i) => [$i->student->id_kelas ?? '~', $i->student->nama_siswa ?? '~'])
            ->values();

        // Paginasi per pengajuan; pencarian data pendukung & pengelompokan kelas hanya untuk halaman ini
        $total = $izinSemua->count();
        $jumlahPerKelas = $izinSemua->countBy(fn($i) => $i->student->id_kelas ?? '(Tanpa Kelas)');
        $halamanIzin = Halaman::dari($izinSemua, 20);
        $izinList = $halamanIzin->getCollection();

        $namaVerifikator = DB::table('users')
            ->whereIn('id_user', $izinList->pluck('verified_by')->filter()->unique())
            ->pluck('nama_lengkap', 'id_user');

        // Sinkron presensi: berapa mapel yang dikoreksi oleh izin ini pada tanggal itu,
        // dan apakah siswanya sudah punya presensi (jurnal guru) hari itu.
        $koreksi = DB::table('attendances')
            ->join('journals', 'attendances.journal_id', '=', 'journals.id')
            ->whereIn('attendances.leave_request_id', $izinList->pluck('id'))
            ->whereDate('journals.date', $tanggal)
            ->select('attendances.leave_request_id', DB::raw('count(*) as total'))
            ->groupBy('attendances.leave_request_id')
            ->pluck('total', 'leave_request_id');

        $presensiSiswa = DB::table('attendances')
            ->join('journals', 'attendances.journal_id', '=', 'journals.id')
            ->whereIn('attendances.student_id', $izinList->pluck('student_id')->unique())
            ->whereDate('journals.date', $tanggal)
            ->select('attendances.student_id', DB::raw('count(*) as total'))
            ->groupBy('attendances.student_id')
            ->pluck('total', 'student_id');

        $laporanPerKelas = $izinList->groupBy(fn($i) => $i->student->id_kelas ?? '(Tanpa Kelas)');

        return view('kesiswaan.laporan_izin', compact(
            'tanggal', 'keyword', 'jenis', 'status', 'kelasFilter', 'kelasWali', 'daftarKelas',
            'ringkasan', 'laporanPerKelas', 'namaVerifikator', 'koreksi', 'presensiSiswa',
            'halamanIzin', 'jumlahPerKelas', 'total'
        ));
    }

    /** Tanggal dari URL: hanya format Y-m-d yang valid, selain itu pakai hari ini. */
    private function tanggalAman($nilai): string
    {
        try {
            $t = \Carbon\Carbon::createFromFormat('Y-m-d', (string) $nilai);
            if ($t && $t->format('Y-m-d') === (string) $nilai) {
                return $t->toDateString();
            }
        } catch (\Throwable $e) {
            // jatuh ke hari ini
        }

        return now()->toDateString();
    }
}
