<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ruangan extends Model {
    protected $table = 'ruangan';
    protected $primaryKey = 'id_ruangan';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}