<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanHarian extends Model
{
    use HasFactory;

    protected $table = 'laporan_harian';

    protected $fillable = [
        'tanggal',
        'total_website',
        'website_aktif',
        'website_error',
        'rata_rata_waktu_respons',
        'path_xlsx',
        'path_pdf'
    ];

    protected $casts = [
        'tanggal' => 'date'
    ];
}
