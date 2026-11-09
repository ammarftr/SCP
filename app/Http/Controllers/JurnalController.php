<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JadwalPelajaran;
use App\Models\Journal;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JurnalController extends Controller
{
    private $hariMap = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', // Sabtu & Minggu libur: tidak dipetakan (hasil null = tidak ada jadwal)
    ];

    public function index(Request $request)
    {
        if (!Auth::check() && !session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Silakan masuk terlebih dahulu!']);
        }

        $guru = $this->currentGuru();

        if (!$guru) {
            return redirect()->route('login')->withErrors(['error' => 'Data guru tidak ditemukan untuk akun ini. Hubungi Waka Kurikulum.']);
        }

        $config = DB::table('system_configurations')->first();
        $hariIni = $this->hariMap[now()->format('l')] ?? null;

        // Dropdown jurnal hanya menampilkan jadwal MILIK GURU INI, HARI INI, di blok aktif — biar ringkas & tidak pusing
        $jadwalHariIni = JadwalPelajaran::query()->with(['kelas', 'mataPelajaran', 'ruangan'])
            ->where('id_guru', $guru->id_guru)
            ->where('hari', $hariIni)
            ->where('cycle_type', $config->active_cycle ?? 'Blok 1')
            ->orderBy('jam_mulai')
            ->get();

        // Preselect dari tombol "Isi Jurnal" di alert dashboard (?schedule_id=xx)
        $preselectId = $request->query('schedule_id');

        // Siapkan daftar siswa per jadwal (buat presensi manual/roll-call di form),
        // lengkap dengan status SARAN otomatis dari izin yang sudah Approved.
        // Guru tetap bisa ubah manual tiap siswa sebelum submit (misal buat menandai Alfa).
        $today = now()->toDateString();
        $rosterPerJadwal = [];
        foreach ($jadwalHariIni as $j) {
            $siswaKelas = Siswa::where('id_kelas', $j->id_kelas)->where('status_utama', 'Aktif')->orderBy('nama_siswa')->get();

            $approvedLeaves = LeaveRequest::where('status_approval', 'Approved')
                ->whereIn('student_id', $siswaKelas->pluck('id_siswa'))
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->get(['student_id', 'jenis', 'reason'])
                ->keyBy('student_id');

            $roster = $siswaKelas->map(function ($s) use ($approvedLeaves) {
                $izin = $approvedLeaves[$s->id_siswa] ?? null;
                $saran = $izin->jenis ?? 'Hadir'; // langsung 'Sakit' atau 'Izin' sesuai pilihan siswa saat mengajukan
                // Keterangan awal diambil dari alasan izin yang sudah disetujui; guru bisa mengubahnya.
                $ket = $izin ? \Illuminate\Support\Str::limit((string) $izin->reason, 150, '') : '';
                return ['id_siswa' => $s->id_siswa, 'nama_siswa' => $s->nama_siswa, 'saran' => $saran, 'keterangan' => $ket];
            });

            $rosterPerJadwal[$j->id_jadwal] = $roster;
        }

        // Riwayat jurnal milik guru ini — terbaru selalu di atas
        $semuaJadwalGuru = JadwalPelajaran::where('id_guru', $guru->id_guru)->pluck('id_jadwal');
        $jurnalList = Journal::with(['jadwal.kelas', 'jadwal.mataPelajaran'])
            ->whereIn('schedule_id', $semuaJadwalGuru)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Daftar hadir tiap jurnal (untuk ringkasan & modal ubah status kehadiran)
        $presensiPerJurnal = DB::table('attendances')
            ->join('siswa', 'attendances.student_id', '=', 'siswa.id_siswa')
            ->whereIn('attendances.journal_id', $jurnalList->getCollection()->pluck('id'))
            ->orderBy('siswa.nama_siswa')
            ->get(['attendances.journal_id', 'attendances.student_id', 'siswa.nama_siswa', 'attendances.status', 'attendances.keterangan', 'attendances.dikoreksi_pada'])
            ->groupBy('journal_id');

        return view('jurnal.index', compact('jadwalHariIni', 'jurnalList', 'guru', 'preselectId', 'rosterPerJadwal', 'presensiPerJurnal'));
    }

    public function store(Request $request)
    {
        if (!Auth::check() && !session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk kembali!']);
        }

        $request->validate([
            'schedule_id' => 'required',
            'topic_description' => 'required',
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $guru = $this->currentGuru();

        $jadwal = JadwalPelajaran::find($request->schedule_id);

        if (!$jadwal || !$guru || $jadwal->id_guru != $guru->id_guru) {
            return redirect()->back()->withErrors(['error' => 'Jadwal tidak valid atau bukan milik Anda.'])->withInput();
        }

        // Jurnal hanya boleh untuk jadwal HARI INI pada BLOK AKTIF (sama dengan yang ditampilkan di form).
        // Dicek di server supaya tidak bisa ditembus dengan mengubah schedule_id di request.
        $blokAktif = DB::table('system_configurations')->value('active_cycle') ?? 'Blok 1';
        $hariIni = $this->hariMap[now()->format('l')] ?? null;
        if ($jadwal->hari !== $hariIni || $jadwal->cycle_type !== $blokAktif) {
            return redirect()->back()->withErrors(['error' => 'Jurnal hanya bisa diisi untuk jadwal hari ini pada ' . $blokAktif . ' (blok aktif).'])->withInput();
        }

        $today = now()->toDateString();

        // Cegah jurnal dobel untuk jadwal & tanggal yang sama (1 jam pelajaran = 1 jurnal per hari)
        $sudahAda = DB::table('journals')
            ->where('schedule_id', $jadwal->id_jadwal)
            ->whereDate('date', $today)
            ->exists();

        if ($sudahAda) {
            return redirect()->back()->withErrors(['error' => 'Jurnal untuk jam pelajaran ini hari ini sudah pernah diisi sebelumnya. Tidak bisa diisi dobel.'])->withInput();
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $file->extension();
            $photoPath = $file->storeAs('jurnal', $filename, 'local');
        }

        DB::beginTransaction();
        try {
            // 1. Simpan Jurnal Utama Guru
            $journalId = DB::table('journals')->insertGetId([
                'schedule_id' => $jadwal->id_jadwal,
                'date' => $today,
                'topic_description' => $request->topic_description,
                'photo_path' => $photoPath,
                'created_at' => now(),
            ]);

            // 2. PRESENSI MANUAL (per mapel/per jam pelajaran, seperti roll-call):
            // Status tiap siswa diambil dari yang SUDAH DIKONFIRMASI Guru di form
            // (defaultnya disarankan otomatis dari izin yang di-Approve, tapi Guru bisa
            // ubah manual — misalnya menandai Alfa untuk siswa yang sebenarnya hadir ke
            // sekolah tapi tidak masuk kelas/kabur saat jam pelajaran berlangsung).
            $siswaKelas = Siswa::where('id_kelas', $jadwal->id_kelas)
                ->where('status_utama', 'Aktif')
                ->get();

            $statusInput = $request->input('attendance', []); // format: [id_siswa => 'Hadir'|'Sakit'|'Izin'|'Alfa']
            $ketInput = $request->input('keterangan', []);   // format: [id_siswa => 'Rapat OSIS'] (hanya dipakai bila tidak hadir)
            $statusValid = ['Hadir', 'Sakit', 'Izin', 'Alfa'];

            // SINKRONISASI IZIN (di sisi server, tidak bergantung pada isi form):
            // siswa yang punya izin Approved pada tanggal jurnal otomatis berstatus Sakit/Izin.
            // Guru tetap boleh menimpa lewat form (mis. siswa ternyata masuk kelas).
            $izinHariIni = LeaveRequest::where('status_approval', 'Approved')
                ->whereIn('student_id', $siswaKelas->pluck('id_siswa'))
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('id')
                ->get()
                ->keyBy('student_id');

            $attendanceRows = [];
            foreach ($siswaKelas as $siswa) {
                $izin = $izinHariIni[$siswa->id_siswa] ?? null;
                $dikirim = $statusInput[$siswa->id_siswa] ?? null;

                // Status dari guru dipakai bila valid; bila kosong/tidak valid, ikuti izin (atau Hadir).
                $status = in_array($dikirim, $statusValid, true) ? $dikirim : ($izin->jenis ?? 'Hadir');

                $ket = $status === 'Hadir' ? '' : trim((string) ($ketInput[$siswa->id_siswa] ?? ''));
                if ($ket === '' && $izin && $status === $izin->jenis) {
                    $ket = \Illuminate\Support\Str::limit((string) $izin->reason, 150, '');
                }

                // Tautkan ke izin hanya bila statusnya memang berasal dari izin itu, supaya
                // pembatalan izin (Rejected/Pending lagi) bisa mengembalikannya lewat status_asal.
                $dariIzin = $izin && $status === $izin->jenis;

                $attendanceRows[] = [
                    'journal_id' => $journalId,
                    'student_id' => $siswa->id_siswa,
                    'date' => $today,
                    'status' => $status,
                    'keterangan' => $ket !== '' ? mb_substr($ket, 0, 255) : null,
                    'status_asal' => $dariIzin ? 'Alfa' : null,
                    'leave_request_id' => $dariIzin ? $izin->id : null,
                ];
            }
            if (count($attendanceRows) > 0) {
                DB::table('attendances')->insert($attendanceRows);
            }

            DB::commit();
            return redirect()->route('jurnal.index')->with('success', 'Jurnal & presensi ' . count($attendanceRows) . ' siswa berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->withErrors(['error' => 'Gagal menyimpan data. Silakan coba lagi.'])->withInput();
        }
    }

    /**
     * Ubah status kehadiran siswa pada jurnal milik guru yang login.
     * Status lama disimpan di status_asal (bila belum ada) beserta siapa & kapan mengubah.
     * Kaitan ke izin dilepas supaya pembatalan izin nanti tidak menimpa koreksi guru.
     */
    public function ubahPresensi(Request $request, $id)
    {
        if (!Auth::check() && !session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk kembali!']);
        }

        $guru = $this->currentGuru();
        $jurnal = Journal::with('jadwal')->find($id);

        if (!$jurnal || !$jurnal->jadwal || !$guru || $jurnal->jadwal->id_guru != $guru->id_guru) {
            return redirect()->route('jurnal.index')->withErrors(['error' => 'Jurnal tidak ditemukan atau bukan milik Anda.']);
        }

        $request->validate([
            'attendance' => 'required|array',
            'attendance.*' => 'in:Hadir,Sakit,Izin,Alfa',
            'keterangan' => 'nullable|array',
            'keterangan.*' => 'nullable|string|max:255',
        ], [
            'attendance.required' => 'Data presensi tidak ditemukan.',
            'attendance.*.in' => 'Status kehadiran harus Hadir, Sakit, Izin, atau Alfa.',
            'keterangan.*.max' => 'Keterangan maksimal 255 karakter.',
        ]);

        $diubah = 0;
        DB::transaction(function () use ($request, $jurnal, &$diubah) {
            $baris = DB::table('attendances')->where('journal_id', $jurnal->id)->get();
            foreach ($baris as $b) {
                $baru = $request->input('attendance.' . $b->student_id);
                if (!$baru) {
                    continue;
                }
                $ket = $baru === 'Hadir' ? '' : trim((string) $request->input('keterangan.' . $b->student_id, ''));
                $ket = $ket !== '' ? $ket : null;

                $statusBerubah = $baru !== $b->status;
                $ketBerubah = ($ket ?? '') !== (string) ($b->keterangan ?? '');
                if (!$statusBerubah && !$ketBerubah) {
                    continue;
                }

                $data = ['keterangan' => $ket];
                if ($statusBerubah) {
                    $data += [
                        'status' => $baru,
                        'status_asal' => $b->status_asal ?? $b->status,
                        'leave_request_id' => null,
                        'dikoreksi_oleh' => session('id_user'),
                        'dikoreksi_pada' => now(),
                    ];
                }
                DB::table('attendances')->where('id', $b->id)->update($data);
                $diubah++;
            }
        });

        return redirect()->route('jurnal.index')->with('success', $diubah > 0
            ? 'Presensi ' . $diubah . ' siswa berhasil diubah.'
            : 'Tidak ada perubahan presensi.');
    }

    /**
     * Rekap Jurnal untuk Waka Kurikulum.
     * Default: hari ini saja. ?riwayat=1 -> semua histori. ?q= -> pencarian.
     */
    public function rekapWaka(Request $request)
    {
        $tampilkanRiwayat = $request->boolean('riwayat');
        $keyword = $request->query('q');

        $query = Journal::with(['jadwal.kelas', 'jadwal.mataPelajaran', 'jadwal.guru']);

        if (!$tampilkanRiwayat) {
            $query->where('date', now()->toDateString());
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('jadwal.guru', fn($sub) => $sub->where('nama_guru', 'like', "%{$keyword}%"))
                  ->orWhereHas('jadwal.kelas', fn($sub) => $sub->where('id_kelas', 'like', "%{$keyword}%"))
                  ->orWhereHas('jadwal.mataPelajaran', fn($sub) => $sub->where('nama_mapel', 'like', "%{$keyword}%"));
            });
        }

        $jurnalList = $query->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('jurnal.rekap_waka', compact('jurnalList', 'tampilkanRiwayat', 'keyword'));
    }
}
