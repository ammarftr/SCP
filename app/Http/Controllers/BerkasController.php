<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Storage;

/**
 * Menyajikan surat izin dan foto jurnal setelah memeriksa hak akses.
 * Berkas baru disimpan di penyimpanan privat (storage), bukan folder publik.
 */
class BerkasController extends Controller
{
    /** Dokumen izin: Wali Kelas, Guru BK (kelas yang diampu), Waka, atau siswa pengaju sendiri. */
    public function izin($id)
    {
        $izin = LeaveRequest::with('student')->findOrFail($id);
        $peran = (int) session('id_role');

        $boleh = in_array($peran, [3, 4, 7], true)
            || ($peran === 5 && (int) $izin->applicant_id === (int) session('id_user'));

        // Guru yang merangkap wali kelas: boleh bila punya kelas perwalian
        if ($peran === 2 && $this->kelasPerwalian() !== '') {
            $boleh = true;
        }
        // Wali Kelas (termasuk guru merangkap) hanya boleh membuka dokumen izin siswa kelas perwaliannya
        if ($boleh && in_array($peran, [2, 3], true)) {
            $boleh = $izin->student && $izin->student->id_kelas === $this->kelasPerwalian();
        }
        // Guru BK hanya boleh membuka dokumen izin siswa kelas yang diampunya
        if ($boleh && $peran === 7) {
            $boleh = $izin->student && in_array((string) $izin->student->id_kelas, $this->kelasBk(), true);
        }
        abort_unless($boleh, 403);

        return $this->kirim($izin->document_path);
    }

    /** Foto jurnal: Waka, atau guru pemilik jurnal. */
    public function jurnal($id)
    {
        $jurnal = Journal::with('jadwal')->findOrFail($id);
        $peran = (int) session('id_role');

        $guru = in_array($peran, [2, 7], true) ? $this->currentGuru() : null;
        $boleh = $peran === 4
            || ($guru && $jurnal->jadwal && (int) $jurnal->jadwal->id_guru === (int) $guru->id_guru);
        abort_unless($boleh, 403);

        return $this->kirim($jurnal->photo_path);
    }

    private function kirim(?string $path)
    {
        abort_if(!$path, 404);

        // Berkas lama masih berada di folder publik (sebelum dipindahkan dengan `php artisan berkas:amankan`)
        if (str_starts_with($path, 'upload')) {
            $penuh = public_path($path);
            abort_unless(is_file($penuh), 404);
            return response()->file($penuh);
        }

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
