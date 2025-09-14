<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory;

    protected $table = 'websites';
    
    protected $fillable = [
        'nama_website',
        'url',
        'aktif',
        'interval_pemeriksaan'
    ];

    public function logPemeriksaan(): HasMany
    {
        return $this->hasMany(LogPemeriksaanWebsite::class);
    }

    public function pemeriksaanTerakhir()
    {
        return $this->hasOne(LogPemeriksaanWebsite::class)->latest();
    }
}