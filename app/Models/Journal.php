<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $table = 'journals';
    protected $guarded = [];
    public $timestamps = false;

    public function jadwal()
    {
        return $this->belongsTo(JadwalPelajaran::class, 'schedule_id');
    }
}