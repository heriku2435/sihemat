<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $guarded = [];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
}
