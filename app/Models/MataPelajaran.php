<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MataPelajaran extends Model {
    protected $table = 'mata_pelajaran';
    protected $primaryKey = 'id_mapel';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}