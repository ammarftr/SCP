<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TahunAjaran extends Model {
    protected $table = 'tahun_ajaran';
    protected $primaryKey = 'id_tahun_ajaran';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}