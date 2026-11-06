<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    protected $table = 'system_configurations';
    protected $guarded = [];
    public $timestamps = false;

    /** Apakah blok tertentu ('Blok 1' / 'Blok 2') sedang dikunci? */
    public function isCycleLocked(?string $cycle): bool
    {
        return match ($cycle) {
            'Blok 1' => (bool) $this->is_locked_blok_1,
            'Blok 2' => (bool) $this->is_locked_blok_2,
            default     => false,
        };
    }
}