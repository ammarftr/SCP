<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JadwalPelajaran;

class MasterDataController extends Controller
{
    public function index()
    {
        $users     = DB::table('users')->orderBy('nama_lengkap')->get();
        $kelas     = DB::table('kelas')->orderBy('id_kelas')->get();
        $jurusan   = DB::table('jurusan')->orderBy('nama_jurusan')->get();
        $mapels    = DB::table('mata_pelajaran')->orderBy('nama_mapel')->get();
        $ruangans  = DB::table('ruangan')->orderBy('id_ruangan')->get();

        $jumlahSiswaPerKelas = DB::table('siswa')->select('id_kelas', DB::raw('count(*) as total'))
            ->groupBy('id_kelas')->pluck('total', 'id_kelas');

        $kelasSiswa = DB::table('siswa')->pluck('id_kelas', 'nisn');
        $daftarBk = $users->where('id_role', 7)->values();
        $namaBk = $daftarBk->pluck('nama_lengkap', 'id_user');
        $waliPerKelas = DB::table('users')->whereIn('id_role', [2, 3, 7])->whereNotNull('id_kelas_wali')
            ->pluck('nama_lengkap', 'id_kelas_wali');

        // kelas => id_user pemegang wali (dipakai form ubah terpisah untuk mengunci kelas yang sudah ada walinya)
        $waliIdPerKelas = DB::table('users')->whereIn('id_role', [2, 3, 7])->whereNotNull('id_kelas_wali')
            ->pluck('id_user', 'id_kelas_wali');

        // Username akun Wali Kelas (peran 3) yang juga mengajar mapel (punya jadwal) -> ikut tampil di tab Guru
        $usernameMengajar = DB::table('jadwal_pelajaran')
            ->join('guru', 'guru.id_guru', '=', 'jadwal_pelajaran.id_guru')
            ->distinct()->pluck('guru.nip')->all();

        // Pengelompokan tab daftar akun. Satu akun bisa muncul di dua tab:
        //  - Guru (2) / Guru BK (7) yang ditetapkan menjadi wali kelas -> tampil juga di tab Wali Kelas
        //  - Wali Kelas (3) yang mengajar mapel (ada jadwal) -> tampil juga di tab Guru
        //  - Guru BK (7) selalu tampil juga di tab Guru (BK dijadwalkan seperti mapel biasa)
        $akunPerTab = [
            4 => $users->where('id_role', 4)->values(),
            2 => $users->filter(fn($u) => (int) $u->id_role === 2
                    || (int) $u->id_role === 7
                    || ((int) $u->id_role === 3 && in_array($u->username, $usernameMengajar, true)))->values(),
            7 => $users->where('id_role', 7)->values(),
            3 => $users->filter(fn($u) => (int) $u->id_role === 3
                    || (in_array((int) $u->id_role, [2, 7], true) && !empty($u->id_kelas_wali)))->values(),
            5 => $users->where('id_role', 5)->values(),
        ];

        return view('waka.master_data', compact('users', 'kelas', 'jurusan', 'mapels', 'ruangans', 'jumlahSiswaPerKelas', 'kelasSiswa', 'waliPerKelas', 'waliIdPerKelas', 'akunPerTab', 'daftarBk', 'namaBk'));
    }

    // ==========================================
    // AKUN
    // ==========================================
    public function storeAkun(Request $request)
    {
        $request->validate([
            'username'     => 'required|unique:users,username',
            'nama_lengkap' => 'required',
            'password'     => 'required|min:8',
            'no_hp'        => 'nullable|string|max:20',
            'id_role'      => 'required|in:2,3,4,5,7',
            'id_kelas'     => 'nullable|exists:kelas,id_kelas',
            'id_kelas_wali' => 'nullable|exists:kelas,id_kelas|unique:users,id_kelas_wali',
        ], [
            'password.min' => 'Kata sandi terlalu pendek! Minimal 8 karakter.',
            'id_kelas_wali.unique' => 'Kelas tersebut sudah punya Wali Kelas lain.',
        ]);

        DB::table('users')->insert([
            'username'      => $request->username,
            'password_hash' => bcrypt($request->password),
            'nama_lengkap'  => $request->nama_lengkap,
            'no_hp'         => $request->no_hp,
            'id_role'       => $request->id_role,
            'is_aktif'      => 1,
            // Kelas perwalian berlaku untuk Wali Kelas dan Guru yang merangkap wali kelas
            'id_kelas_wali' => in_array((int) $request->id_role, [2, 3, 7]) ? ($request->id_kelas_wali ?: null) : null,
        ]);

        // Waka, Guru, Wali Kelas, Guru BK -> semuanya guru/tenaga pendidik,
        // jadi otomatis terdaftar ke tabel master guru (nip = username).
        // Ini juga yang bikin satu orang bisa punya 2 akun berbeda (mis. akun Guru Mapel & akun Wali Kelas)
        // karena masing-masing akun independen sebagai baris guru sendiri.
        if (in_array($request->id_role, [2, 3, 4, 7])) {
            $cekGuru = DB::table('guru')->where('nip', $request->username)->exists();
            if (!$cekGuru) {
                DB::table('guru')->insert([
                    'nip'       => $request->username,
                    'nama_guru' => $request->nama_lengkap,
                ]);
            }
        }

        // Siswa -> otomatis terdaftar ke tabel master siswa (nisn = username), sekalian pilih kelasnya
        if ($request->id_role == 5) {
            $cekSiswa = DB::table('siswa')->where('nisn', $request->username)->exists();
            if (!$cekSiswa) {
                DB::table('siswa')->insert([
                    'nisn'         => $request->username,
                    'nama_siswa'   => $request->nama_lengkap,
                    'id_kelas'     => $request->id_kelas,
                    'status_utama' => 'Aktif',
                ]);
            }
        }

        return redirect()->route('akun.index')->with('success', 'Akun dibuat.');
    }

    public function updateAkun(Request $request, $id)
    {
        $request->validate([
            'nama_lengkap' => 'required',
            'id_role'      => 'required|in:2,3,4,5,7',
            'password'     => 'nullable|min:8',
            'no_hp'        => 'nullable|string|max:20',
            'id_kelas'     => 'nullable|exists:kelas,id_kelas',
            'id_kelas_wali' => 'nullable|exists:kelas,id_kelas|unique:users,id_kelas_wali,' . $id . ',id_user',
        ], [
            'password.min' => 'Kata sandi terlalu pendek! Minimal 8 karakter.',
            'id_kelas_wali.unique' => 'Kelas tersebut sudah punya Wali Kelas lain.',
        ]);

        $data = [
            'nama_lengkap' => $request->nama_lengkap,
            'no_hp'        => $request->no_hp,
            'id_role'      => $request->id_role,
            'id_kelas_wali' => in_array((int) $request->id_role, [2, 3, 7]) ? ($request->id_kelas_wali ?: null) : null,
        ];

        if ($request->filled('password')) {
            $data['password_hash'] = bcrypt($request->password);
            $data['password_changed_at'] = now();   // sesi lama akun ini otomatis dikeluarkan
        }

        DB::table('users')->where('id_user', $id)->update($data);

        // Sinkronkan juga nama & kelas ke tabel master terkait (kalau ada)
        $username = DB::table('users')->where('id_user', $id)->value('username');
        if (in_array((int) $request->id_role, [2, 3, 4, 7]) && !DB::table('guru')->where('nip', $username)->exists()) {
            DB::table('guru')->insert(['nip' => $username, 'nama_guru' => $request->nama_lengkap]);
        }
        DB::table('guru')->where('nip', $username)->update(['nama_guru' => $request->nama_lengkap]);
        $dataSiswa = ['nama_siswa' => $request->nama_lengkap];
        if ($request->filled('id_kelas')) {
            $dataSiswa['id_kelas'] = $request->id_kelas;
        }
        DB::table('siswa')->where('nisn', $username)->update($dataSiswa);

        return redirect()->route('akun.index')->with('success', 'Akun diperbarui.');
    }

    public function destroyAkun($id)
    {
        if ($id == session('id_user')) {
            return redirect()->route('akun.index')->withErrors(['error' => 'Tidak bisa menghapus akun yang sedang Anda gunakan.']);
        }

        $username = DB::table('users')->where('id_user', $id)->value('username');

        DB::beginTransaction();
        try {
            // Hapus juga data master terkait (guru/siswa) supaya tidak ada data
            // "nyangkut" yang bikin tampilan nampilin teks "Tidak Ditemukan".
            // FK di jadwal_pelajaran/leave_requests/attendances sudah ON DELETE CASCADE,
            // jadi data turunannya (jadwal, jurnal, presensi, izin) ikut bersih otomatis.
            DB::table('guru')->where('nip', $username)->delete();
            DB::table('siswa')->where('nisn', $username)->delete();
            DB::table('users')->where('id_user', $id)->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return redirect()->route('akun.index')->withErrors(['error' => 'Gagal menghapus akun. Silakan coba lagi.']);
        }

        return redirect()->route('akun.index')->with('success', 'Akun dan data terkait dihapus.');
    }

    // ==========================================
    // KELAS
    // ==========================================
    public function storeKelas(Request $request)
    {
        $request->validate([
            'id_kelas'   => 'required|unique:kelas,id_kelas',
            'tingkat'    => 'required|in:X,XI,XII',
            'id_jurusan' => 'nullable',
            'id_bk'      => 'nullable|exists:users,id_user,id_role,7',
        ]);

        DB::table('kelas')->insert([
            'id_kelas'   => $request->id_kelas,
            'tingkat'    => $request->tingkat,
            'id_jurusan' => $request->id_jurusan ?: null,
            'id_bk'      => $request->id_bk ?: null,
        ]);

        return redirect()->route('akun.index')->with('success', 'Kelas ditambahkan.');
    }

    public function updateKelas(Request $request, $id)
    {
        $request->validate([
            'tingkat'    => 'required|in:X,XI,XII',
            'id_jurusan' => 'nullable',
            'id_bk'      => 'nullable|exists:users,id_user,id_role,7',
        ]);

        DB::table('kelas')->where('id_kelas', $id)->update([
            'tingkat'    => $request->tingkat,
            'id_jurusan' => $request->id_jurusan ?: null,
            'id_bk'      => $request->id_bk ?: null,
        ]);

        return redirect()->route('akun.index')->with('success', 'Kelas diperbarui.');
    }

    public function destroyKelas($id)
    {
        DB::table('kelas')->where('id_kelas', $id)->delete();

        return redirect()->route('akun.index')->with('success', 'Kelas dihapus.');
    }

    // ==========================================
    // MATA PELAJARAN
    // ==========================================
    public function storeMapel(Request $request)
    {
        $request->validate([
            'id_mapel'   => 'required|unique:mata_pelajaran,id_mapel',
            'nama_mapel' => 'required',
        ]);

        DB::table('mata_pelajaran')->insert($request->only(['id_mapel', 'nama_mapel']));

        return redirect()->route('akun.index')->with('success', 'Mata pelajaran ditambahkan.');
    }

    public function updateMapel(Request $request, $id)
    {
        $request->validate(['nama_mapel' => 'required']);

        DB::table('mata_pelajaran')->where('id_mapel', $id)->update(['nama_mapel' => $request->nama_mapel]);

        return redirect()->route('akun.index')->with('success', 'Mata pelajaran diperbarui.');
    }

    public function destroyMapel($id)
    {
        DB::table('mata_pelajaran')->where('id_mapel', $id)->delete();

        return redirect()->route('akun.index')->with('success', 'Mata pelajaran dihapus.');
    }

    // ==========================================
    // RUANGAN
    // ==========================================
    public function storeRuangan(Request $request)
    {
        $request->validate([
            'id_ruangan'   => 'required|unique:ruangan,id_ruangan',
            'nama_ruangan' => 'required',
            'jenis'        => 'nullable|in:Ruangan,Lab',
        ]);

        DB::table('ruangan')->insert($request->only(['id_ruangan', 'nama_ruangan', 'jenis']));

        return redirect()->route('akun.index')->with('success', 'Ruangan ditambahkan.');
    }

    public function updateRuangan(Request $request, $id)
    {
        $request->validate([
            'nama_ruangan' => 'required',
            'jenis'        => 'nullable|in:Ruangan,Lab',
        ]);

        DB::table('ruangan')->where('id_ruangan', $id)->update($request->only(['nama_ruangan', 'jenis']));

        return redirect()->route('akun.index')->with('success', 'Ruangan diperbarui.');
    }

    public function destroyRuangan($id)
    {
        DB::table('ruangan')->where('id_ruangan', $id)->delete();

        return redirect()->route('akun.index')->with('success', 'Ruangan dihapus.');
    }

    // ==========================================
    // JADWAL (Waka hanya boleh memantau & menghapus, input tetap oleh Guru)
    // ==========================================
    public function destroyJadwal($id)
    {
        // Jurnal & presensi ikut terhapus bila jadwal dihapus (cascade), jadi ditolak.
        if (DB::table('journals')->where('schedule_id', $id)->exists()) {
            return redirect()->back()->withErrors(['error' => 'Jadwal ini sudah punya jurnal, jadi tidak bisa dihapus.']);
        }

        JadwalPelajaran::where('id_jadwal', $id)->delete();

        return redirect()->back()->with('success', 'Jadwal dihapus.');
    }

    // ==========================================
    // AKSI MASSAL (hapus / ubah banyak data sekaligus)
    // ==========================================
    private function idTerpilih(Request $request): array
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'required|string'], [
            'ids.required' => 'Belum ada data yang dipilih.',
            'ids.min' => 'Belum ada data yang dipilih.',
        ]);

        return array_values(array_unique($request->input('ids')));
    }

    /** Hapus satu per satu supaya yang gagal tidak membatalkan yang lain. */
    private function hapusMassal(array $ids, callable $hapus, string $nama)
    {
        $ok = 0;
        $gagal = 0;
        foreach ($ids as $id) {
            try {
                $hapus($id);
                $ok++;
            } catch (\Throwable $e) {
                report($e);
                $gagal++;
            }
        }
        $pesan = "$ok $nama dihapus." . ($gagal ? " $gagal gagal dihapus." : '');

        return $ok ? redirect()->back()->with('success', $pesan) : redirect()->back()->withErrors(['error' => $pesan]);
    }

    public function hapusMassalAkun(Request $request)
    {
        $ids = array_filter($this->idTerpilih($request), fn($i) => (string) $i !== (string) session('id_user'));
        $ada = count($ids);

        $hasil = $this->hapusMassal($ids, function ($id) {
            DB::transaction(function () use ($id) {
                $username = DB::table('users')->where('id_user', $id)->value('username');
                if ($username === null) return;
                DB::table('guru')->where('nip', $username)->delete();
                DB::table('siswa')->where('nisn', $username)->delete();
                DB::table('users')->where('id_user', $id)->delete();
            });
        }, 'akun');

        return $ada ? $hasil : redirect()->back()->withErrors(['error' => 'Akun yang sedang Anda pakai tidak bisa dihapus.']);
    }

    public function ubahMassalAkun(Request $request)
    {
        $ids = $this->idTerpilih($request);
        $request->validate([
            'id_kelas' => 'nullable|exists:kelas,id_kelas',
            'password' => 'nullable|min:8',
        ], ['password.min' => 'Kata sandi terlalu pendek! Minimal 8 karakter.']);

        if (!$request->filled('id_kelas') && !$request->filled('password')) {
            return redirect()->back()->withErrors(['error' => 'Isi minimal satu kolom yang ingin diubah.']);
        }

        $pesan = [];
        $users = DB::table('users')->whereIn('id_user', $ids)->get(['id_user', 'username', 'id_role']);

        if ($request->filled('id_kelas')) {
            $nisn = $users->where('id_role', 5)->pluck('username')->all();
            $n = $nisn ? DB::table('siswa')->whereIn('nisn', $nisn)->update(['id_kelas' => $request->id_kelas]) : 0;
            $pesan[] = "Kelas $request->id_kelas dipasang ke $n siswa (akun non-siswa dilewati).";
        }
        if ($request->filled('password')) {
            $target = $users->pluck('id_user')->reject(fn($i) => (string) $i === (string) session('id_user'))->all();
            if ($target) {
                DB::table('users')->whereIn('id_user', $target)->update([
                    'password_hash' => bcrypt($request->password),
                    'password_changed_at' => now(),
                ]);
            }
            $pesan[] = 'Kata sandi diganti untuk ' . count($target) . ' akun (akun Anda sendiri dilewati).';
        }

        return redirect()->back()->with('success', implode(' ', $pesan));
    }

    /**
     * Ubah banyak akun sekaligus, tiap akun punya isian sendiri (seperti edit banyak baris di phpMyAdmin).
     * Input: akun[<id_user>][nama_lengkap|no_hp|id_role|id_kelas_wali|password]
     * Semua-atau-tidak-sama-sekali: kalau ada satu yang bermasalah, tidak ada yang tersimpan.
     */
    public function ubahTerpisahAkun(Request $request)
    {
        $request->validate([
            'akun'                  => 'required|array|min:1',
            'akun.*.nama_lengkap'   => 'required|string|max:100',
            'akun.*.id_role'        => 'required|in:2,3,4,7',
            'akun.*.no_hp'          => 'nullable|string|max:20',
            'akun.*.id_kelas_wali'  => 'nullable|exists:kelas,id_kelas',
            'akun.*.password'       => 'nullable|min:8',
        ], [
            'akun.required'                => 'Belum ada akun yang dipilih.',
            'akun.*.nama_lengkap.required' => 'Nama lengkap tidak boleh kosong.',
            'akun.*.password.min'          => 'Kata sandi terlalu pendek! Minimal 8 karakter.',
            'akun.*.id_kelas_wali.exists'  => 'Kelas perwalian tidak ditemukan.',
        ]);

        $data  = $request->input('akun');
        $ids   = array_map('strval', array_keys($data));
        $users = DB::table('users')->whereIn('id_user', $ids)->get()->keyBy(fn($u) => (string) $u->id_user);

        foreach ($ids as $id) {
            if (!$users->has($id) || (int) $users[$id]->id_role === 5) {
                return redirect()->back()->withErrors(['error' => 'Ada akun yang tidak valid (tidak ditemukan atau akun Siswa).']);
            }
        }

        // Kelas perwalian akhir tiap akun (hanya peran 2/3/7 yang boleh punya)
        $wali = [];
        foreach ($data as $id => $f) {
            $k = in_array((int) $f['id_role'], [2, 3, 7], true) ? ($f['id_kelas_wali'] ?? null) : null;
            $wali[(string) $id] = $k ?: null;
        }

        // 1) Dua akun dalam satu batch tidak boleh memilih kelas yang sama
        $dipakai = [];
        foreach ($wali as $id => $k) {
            if ($k === null) continue;
            if (isset($dipakai[$k])) {
                return redirect()->back()->withErrors(['error' => "Kelas $k dipilih untuk dua akun sekaligus ({$users[$dipakai[$k]]->nama_lengkap} dan {$users[$id]->nama_lengkap}). Satu kelas hanya boleh punya satu wali."]);
            }
            $dipakai[$k] = $id;
        }

        // 2) Kelas tidak boleh sudah dipegang wali lain di luar batch ini
        $bentrok = DB::table('users')->whereIn('id_kelas_wali', array_keys($dipakai))
            ->whereNotIn('id_user', $ids)->pluck('nama_lengkap', 'id_kelas_wali');
        if ($bentrok->isNotEmpty()) {
            $daftar = $bentrok->map(fn($nama, $k) => "$k (wali: $nama)")->implode(', ');
            return redirect()->back()->withErrors(['error' => "Kelas sudah punya Wali Kelas lain: $daftar."]);
        }

        DB::transaction(function () use ($data, $users, $ids, $wali) {
            // Lepas dulu semua perwalian milik batch ini, supaya tukar kelas antar akun tidak bentrok di kolom unik
            DB::table('users')->whereIn('id_user', $ids)->update(['id_kelas_wali' => null]);

            foreach ($data as $id => $f) {
                $id = (string) $id;
                $baris = [
                    'nama_lengkap'  => $f['nama_lengkap'],
                    'no_hp'         => $f['no_hp'] ?? null,
                    'id_role'       => $f['id_role'],
                    'id_kelas_wali' => $wali[$id],
                ];
                if (!empty($f['password'])) {
                    $baris['password_hash'] = bcrypt($f['password']);
                    $baris['password_changed_at'] = now();
                }
                DB::table('users')->where('id_user', $id)->update($baris);

                $username = $users[$id]->username;
                if (!DB::table('guru')->where('nip', $username)->exists()) {
                    DB::table('guru')->insert(['nip' => $username, 'nama_guru' => $f['nama_lengkap']]);
                }
                DB::table('guru')->where('nip', $username)->update(['nama_guru' => $f['nama_lengkap']]);
            }
        });

        return redirect()->back()->with('success', count($data) . ' akun diperbarui.');
    }

    public function hapusMassalKelas(Request $request)
    {
        return $this->hapusMassal($this->idTerpilih($request), fn($id) => DB::table('kelas')->where('id_kelas', $id)->delete(), 'kelas');
    }

    public function ubahMassalKelas(Request $request)
    {
        $ids = $this->idTerpilih($request);
        $request->validate(['tingkat' => 'nullable|in:X,XI,XII', 'id_jurusan' => 'nullable|string', 'id_bk' => 'nullable|string']);

        $data = [];
        if ($request->filled('id_bk')) {
            if ($request->id_bk === '-') {
                $data['id_bk'] = null;
            } elseif (DB::table('users')->where('id_user', $request->id_bk)->where('id_role', 7)->exists()) {
                $data['id_bk'] = (int) $request->id_bk;
            } else {
                return redirect()->back()->withErrors(['error' => 'Akun Guru BK tidak ditemukan.']);
            }
        }
        if ($request->filled('tingkat')) $data['tingkat'] = $request->tingkat;
        if ($request->filled('id_jurusan')) {
            if ($request->id_jurusan === '-') {
                $data['id_jurusan'] = null;
            } elseif (DB::table('jurusan')->where('id_jurusan', $request->id_jurusan)->exists()) {
                $data['id_jurusan'] = $request->id_jurusan;
            } else {
                return redirect()->back()->withErrors(['error' => 'Jurusan tidak ditemukan.']);
            }
        }
        if (!$data) return redirect()->back()->withErrors(['error' => 'Isi minimal satu kolom yang ingin diubah.']);

        $n = DB::table('kelas')->whereIn('id_kelas', $ids)->update($data);

        return redirect()->back()->with('success', "$n kelas diperbarui.");
    }

    public function hapusMassalMapel(Request $request)
    {
        return $this->hapusMassal($this->idTerpilih($request), fn($id) => DB::table('mata_pelajaran')->where('id_mapel', $id)->delete(), 'mata pelajaran');
    }

    public function hapusMassalRuangan(Request $request)
    {
        return $this->hapusMassal($this->idTerpilih($request), fn($id) => DB::table('ruangan')->where('id_ruangan', $id)->delete(), 'ruangan');
    }

    public function ubahMassalRuangan(Request $request)
    {
        $ids = $this->idTerpilih($request);
        $request->validate(['jenis' => 'required|in:Ruangan,Lab'], ['jenis.required' => 'Pilih jenis ruangan.']);

        $n = DB::table('ruangan')->whereIn('id_ruangan', $ids)->update(['jenis' => $request->jenis]);

        return redirect()->back()->with('success', "$n ruangan diperbarui.");
    }

    public function hapusMassalJadwal(Request $request)
    {
        $ids = array_map('intval', $this->idTerpilih($request));
        $berjurnal = DB::table('journals')->whereIn('schedule_id', $ids)->pluck('schedule_id')->all();
        $boleh = array_values(array_diff($ids, $berjurnal));

        $n = $boleh ? JadwalPelajaran::whereIn('id_jadwal', $boleh)->delete() : 0;
        $pesan = "$n jadwal dihapus." . ($berjurnal ? ' ' . count($berjurnal) . ' jadwal dilewati karena sudah punya jurnal.' : '');

        return $n ? redirect()->back()->with('success', $pesan) : redirect()->back()->withErrors(['error' => $pesan]);
    }
}
