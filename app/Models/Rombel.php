<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rombel extends Model
{
    protected $guarded = [];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function siswas()
    {
        return $this->belongsToMany(Siswa::class, 'rombel_siswa')
                    ->withPivot(['id', 'nomor_urut'])
                    ->withTimestamps();
    }
}
