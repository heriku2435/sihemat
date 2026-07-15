<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetoranKoperasi extends Model
{
    protected $guarded = [];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
}
