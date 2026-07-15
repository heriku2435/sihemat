<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    protected $guarded = [];

    protected $appends = ['computed_status', 'saldo'];

    public function transaksis()
    {
        return $this->hasMany(Transaksi::class);
    }

    public function rombels()
    {
        return $this->belongsToMany(Rombel::class, 'rombel_siswa')
                    ->withPivot(['id', 'nomor_urut'])
                    ->withTimestamps();
    }

    public function getComputedStatusAttribute()
    {
        $status = $this->attributes['status'] ?? 'Aktif';
        
        // Jika status di database adalah Aktif, tapi riwayat kelas > 6, otomatis Lulus
        if ($status === 'Aktif') {
            if ($this->rombels()->count() > 6) {
                return 'Lulus';
            }
        }
        
        return $status;
    }

    public function getSaldoAttribute()
    {
        $setor = $this->transaksis()->where('jenis', 'setor')->sum('jumlah');
        $tarik = $this->transaksis()->where('jenis', 'tarik')->sum('jumlah');
        return $setor - $tarik;
    }
}
