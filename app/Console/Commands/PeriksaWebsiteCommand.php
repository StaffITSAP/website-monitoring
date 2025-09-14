<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitorWebsiteService;

class PeriksaWebsiteCommand extends Command
{
    protected $signature = 'website:periksa';
    protected $description = 'Memeriksa status semua website yang aktif';

    public function handle(MonitorWebsiteService $monitorService)
    {
        $this->info('Memulai pemeriksaan website...');

        try {
            $monitorService->periksaSemuaWebsite();
            $this->info('Pemeriksaan website selesai.');

            // Buat laporan harian setelah pemeriksaan
            $this->call('laporan:harian');
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
