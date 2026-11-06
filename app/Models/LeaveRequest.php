<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'leave_requests';

    /** Batas pengajuan izin terlambat: maksimal berapa hari setelah tanggal tidak hadir. */
    public const MAKS_HARI_TERLAMBAT = 3;

    protected $fillable = [
        'student_id',
        'applicant_id',
        'jenis',
        'start_date',
        'end_date',
        'reason',
        'document_path',
        'status_approval',
        'verified_by',
        'alasan_penolakan',
    ];

    public function student()
    {
        return $this->belongsTo(Siswa::class, 'student_id', 'id_siswa');
    }
}