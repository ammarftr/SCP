<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Kelas extends Model {
    protected $table = 'kelas';
    protected $primaryKey = 'id_kelas';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}