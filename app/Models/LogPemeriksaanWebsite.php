<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LogPemeriksaanWebsite extends Model
{
    use HasFactory;

    protected $table = 'log_pemeriksaan_website';

    protected $fillable = [
        'website_id',
        'kode_status',
        'waktu_respons',
        'berhasil',
        'pesan_error',
        'screenshot_path'
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
    public function getScreenshotUrlAttribute()
    {
        if ($this->screenshot_path && Storage::disk('public')->exists($this->screenshot_path)) {
            return Storage::disk('public')->url($this->screenshot_path);
        }
        return null;
    }

    public function hasScreenshot()
    {
        return !empty($this->screenshot_url);
    }
}
