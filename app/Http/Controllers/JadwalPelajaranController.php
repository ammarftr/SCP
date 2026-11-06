<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JadwalPelajaran;
use App\Models\SystemConfiguration;
use App\Models\Journal;
use App\Models\Guru;
use App\Support\SesiPelajaran;

class JadwalPelajaranController extends Controller
{
    public function index(Request $request)
    {
        // PENGAMAN ANTI-BOBOL: Pastikan user sudah login
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Silakan masuk terlebih dahulu!']);
        }

        $idUser = session('id_user');
        $idRole = session('id_role'); 

        $guruLogin = $this->currentGuru();

        // Ambil data pendukung
        $kelas = DB::table('kelas')->get();
        $mapels = DB::table('mata_pelajaran')->get(); 
        $ruangans = DB::table('ruangan')->get();
        $config = SystemConfiguration::first();

        // Cek Ketersediaan Jadwal: sengaja menampilkan jadwal SEMUA guru (bukan cuma
        // milik sendiri), biar guru bisa cek "jam segini ruang ini dipakai siapa/enggak"
        // sebelum input jadwal baru sendiri — mendukung fitur anti-tabrakan secara visual.
        // Bisa difilter per Hari, Kelas, dan Ruangan. Blok ditampilkan satu per satu di tampilan.
        $cekQuery = JadwalPelajaran::with(['kelas', 'mataPelajaran', 'guru', 'ruangan']);

        if ($request->filled('cek_hari')) {
            $cekQuery->where('hari', $request->cek_hari);
        }
        if ($request->filled('cek_kelas')) {
            $cekQuery->where('id_kelas', $request->cek_kelas);
        }
        if ($request->filled('cek_ruangan')) {
            $cekQuery->where('id_ruangan_default', $request->cek_ruangan);
        }

        $urutanHari = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5];
        $jadwals = $cekQuery->get()->sortBy(fn($j) => ($urutanHari[$j->hari] ?? 9) . '_' . $j->jam_mulai)->values();

        $filterAktif = $request->only(['cek_hari', 'cek_kelas', 'cek_ruangan']);

        // Hasil hanya menampilkan satu blok, mengikuti Blok pada form input:
        // gagal simpan -> blok yang tadi dipilih; berhasil simpan -> blok yang baru diinput
        // (lewat cek_cycle); selain itu blok aktif (atau blok pertama yang belum terkunci).
        $kunci = [
            'Blok 1' => (bool) ($config->is_locked_blok_1 ?? false),
            'Blok 2' => (bool) ($config->is_locked_blok_2 ?? false),
        ];
        $aktif = $config->active_cycle ?? 'Blok 1';
        $blokBawaan = !$kunci[$aktif] ? $aktif : (array_search(false, $kunci, true) ?: $aktif);
        $blokDipilih = session()->getOldInput('cycle_type') ?: $request->query('cek_cycle');
        $blokTampil = (is_string($blokDipilih) && isset($kunci[$blokDipilih])) ? $blokDipilih : $blokBawaan;

        if ($guruLogin) {
            $jadwalMilikSendiri = JadwalPelajaran::where('id_guru', $guruLogin->id_guru)->pluck('id_jadwal');
            $jurnalList = Journal::with(['jadwal.kelas', 'jadwal.mataPelajaran'])
                ->whereIn('schedule_id', $jadwalMilikSendiri)
                ->get(); 
        } else {
            $jurnalList = Journal::with(['jadwal.kelas', 'jadwal.mataPelajaran'])->get(); 
        }
        
        return view('jadwal.index', compact('jadwals', 'jurnalList', 'config', 'kelas', 'mapels', 'ruangans', 'guruLogin', 'filterAktif', 'blokTampil'));
    }

    /**
     * Kembali ke halaman jadwal dengan Hasil langsung menampilkan blok yang baru diinput/diubah.
     * Filter Hari, Kelas, dan Ruangan yang sedang dipakai tetap dipertahankan.
     */
    private function kembaliKeJadwal(string $blok)
    {
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);
        $query = array_intersect_key($query, array_flip(['cek_hari', 'cek_kelas', 'cek_ruangan']));

        return redirect()->route('jadwal.index', $query + ['cek_cycle' => $blok]);
    }

    /** Aturan validasi input jadwal (dipakai tambah & ubah). */
    private function aturanJadwal(): array
    {
        return [
            'id_kelas' => 'required',
            'id_mapel' => 'required',
            'id_guru' => 'required',
            'id_ruangan_default' => 'required',
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat',
            'sesi_mulai' => 'required|integer|min:1|max:10',
            'sesi_selesai' => 'required|integer|min:1|max:10|gte:sesi_mulai',
            'cycle_type' => 'required|in:Blok 1,Blok 2',
        ];
    }

    private function pesanJadwal(): array
    {
        return [
            'sesi_mulai.required' => 'Pilih jam ke- mulai.',
            'sesi_selesai.required' => 'Pilih jam ke- selesai.',
            'sesi_selesai.gte' => 'Sesi selesai harus sama dengan atau setelah sesi mulai.',
        ];
    }

    /**
     * Ubah sesi (jam ke-) menjadi jam_mulai & jam_selesai sesuai hari, lalu gabungkan ke request.
     * Mengembalikan pesan galat bila rentang sesi tidak valid, atau null bila berhasil.
     */
    private function terapkanSesi(Request $request): ?string
    {
        $jam = SesiPelajaran::hitung($request->hari, (int) $request->sesi_mulai, (int) $request->sesi_selesai);
        if (is_string($jam)) {
            return $jam;
        }
        $request->merge(['jam_mulai' => $jam['mulai'], 'jam_selesai' => $jam['selesai']]);

        return null;
    }

    /**
     * Cek irisan waktu (Start_A < End_B AND End_A > Start_B) pada blok & hari yang sama
     * untuk ruangan, kelas, atau guru yang sama. $kecualiId dipakai saat ubah jadwal
     * supaya jadwal itu tidak dianggap bentrok dengan dirinya sendiri.
     */
    private function adaBentrok(Request $request, ?int $kecualiId = null): bool
    {
        return JadwalPelajaran::where('hari', $request->hari)
            ->where('cycle_type', $request->cycle_type)
            ->when($kecualiId, fn($q) => $q->where('id_jadwal', '!=', $kecualiId))
            ->where(function ($query) use ($request) {
                $query->where('id_ruangan_default', $request->id_ruangan_default)
                      ->orWhere('id_kelas', $request->id_kelas)
                      ->orWhere('id_guru', $request->id_guru);
            })
            ->where('jam_mulai', '<', $request->jam_selesai)
            ->where('jam_selesai', '>', $request->jam_mulai)
            ->exists();
    }

    public function store(Request $request)
    {
        if (!session()->has('id_user')) {
            return redirect()->route('login')->withErrors(['error' => 'Sesi habis, silakan masuk kembali!']);
        }

        // Guru SELALU diambil dari akun yang login (input dari form diabaikan)
        $guruLogin = $this->currentGuru();
        if (!$guruLogin) {
            return redirect()->back()->withErrors(['error' => 'Akun Anda belum terhubung dengan data guru. Hubungi Waka Kurikulum.'])->withInput();
        }
        $request->merge(['id_guru' => $guruLogin->id_guru]);

        $request->validate($this->aturanJadwal(), $this->pesanJadwal());

        if ($galat = $this->terapkanSesi($request)) {
            return redirect()->back()->withErrors(['error' => $galat])->withInput();
        }

        // Kunci jadwal berlaku PER BLOK: kalau Blok 1 dikunci, guru masih
        // boleh input jadwal Blok 2 (dan sebaliknya).
        $config = SystemConfiguration::first();
        if ($config && $config->isCycleLocked($request->cycle_type)) {
            return redirect()->back()
                ->withErrors(['error' => 'GAGAL! Jadwal ' . $request->cycle_type . ' sedang dikunci oleh Waka Kurikulum. Silakan pilih blok lain yang masih terbuka.'])
                ->withInput();
        }

        if ($this->adaBentrok($request)) {
            return redirect()->back()->withErrors(['error' => 'GAGAL! Jadwal bentrok dengan waktu, ruangan, atau kelas lain pada blok ini.'])->withInput();
        }

        JadwalPelajaran::create($request->only([
            'id_kelas', 'id_mapel', 'id_guru',
            'id_ruangan_default', 'hari', 'jam_mulai', 'jam_selesai', 'cycle_type',
        ]) + ['id_tahun_ajaran' => $this->tahunAjaranBawaan()]);

        return $this->kembaliKeJadwal($request->cycle_type)->with('success', 'Jadwal pelajaran berhasil ditambahkan!');
    }

    /**
     * Ambil jadwal milik guru yang login & pastikan boleh diubah.
     * Mengembalikan [jadwal, null] bila boleh, atau [null, redirect-error] bila ditolak.
     * Pengecekan ini di sisi server, jadi tetap aman walau tombol di tampilan disembunyikan.
     */
    private function jadwalBolehDiubah($id): array
    {
        $guruLogin = $this->currentGuru();
        $jadwal = JadwalPelajaran::where('id_jadwal', $id)->first();

        if (!$jadwal) {
            return [null, redirect()->route('jadwal.index')->withErrors(['error' => 'Jadwal tidak ditemukan.'])];
        }
        if (!$guruLogin || $jadwal->id_guru != $guruLogin->id_guru) {
            return [null, redirect()->route('jadwal.index')->withErrors(['error' => 'Anda hanya boleh mengubah jadwal milik Anda sendiri.'])];
        }

        $config = SystemConfiguration::first();
        if ($config && $config->isCycleLocked($jadwal->cycle_type)) {
            return [null, redirect()->route('jadwal.index')->withErrors(['error' => 'GAGAL! Jadwal ' . $jadwal->cycle_type . ' sedang dikunci oleh Waka Kurikulum, sehingga tidak bisa diubah atau dihapus.'])];
        }

        return [$jadwal, null];
    }

    public function update(Request $request, $id)
    {
        [$jadwal, $tolak] = $this->jadwalBolehDiubah($id);
        if ($tolak) {
            return $tolak;
        }

        // Guru tetap pemilik jadwal; tidak bisa dialihkan ke guru lain lewat form.
        $request->merge(['id_guru' => $jadwal->id_guru]);
        $request->validate($this->aturanJadwal(), $this->pesanJadwal());

        if ($galat = $this->terapkanSesi($request)) {
            return redirect()->back()->withErrors(['error' => $galat]);
        }

        // Jadwal tidak boleh dipindah ke blok yang sedang dikunci.
        $config = SystemConfiguration::first();
        if ($config && $config->isCycleLocked($request->cycle_type)) {
            return redirect()->back()
                ->withErrors(['error' => 'GAGAL! ' . $request->cycle_type . ' sedang dikunci oleh Waka Kurikulum, jadwal tidak bisa dipindahkan ke sana.']);
        }

        if ($this->adaBentrok($request, (int) $jadwal->id_jadwal)) {
            return redirect()->back()->withErrors(['error' => 'GAGAL! Perubahan jadwal bentrok dengan waktu, ruangan, atau kelas lain pada blok ini.']);
        }

        $jadwal->update($request->only([
            'id_kelas', 'id_mapel',
            'id_ruangan_default', 'hari', 'jam_mulai', 'jam_selesai', 'cycle_type',
        ]));

        return $this->kembaliKeJadwal($request->cycle_type)->with('success', 'Jadwal berhasil diperbarui!');
    }

    public function destroy($id)
    {
        [$jadwal, $tolak] = $this->jadwalBolehDiubah($id);
        if ($tolak) {
            return $tolak;
        }

        // Jurnal & presensi ikut terhapus bila jadwal dihapus (cascade), jadi ditolak.
        if (Journal::where('schedule_id', $jadwal->id_jadwal)->exists()) {
            return redirect()->route('jadwal.index')->withErrors(['error' => 'GAGAL! Jadwal ini sudah memiliki jurnal mengajar, sehingga tidak bisa dihapus agar data jurnal & presensi tetap aman.']);
        }

        $jadwal->delete();

        return redirect()->route('jadwal.index')->with('success', 'Jadwal berhasil dihapus!');
    }
}
