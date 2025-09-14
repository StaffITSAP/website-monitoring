<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MonitorWebsiteService;

class BuatLaporanHarianCommand extends Command
{
    protected $signature = 'laporan:harian {--tanggal=}';
    protected $description = 'Membuat laporan harian monitoring website';

    public function handle(MonitorWebsiteService $monitorService)
    {
        $tanggal = $this->option('tanggal') ?: now()->format('Y-m-d');

        $this->info("Membuat laporan harian untuk tanggal {$tanggal}...");

        try {
            $laporan = $monitorService->buatLaporanHarian($tanggal);
            $this->info("Laporan harian untuk tanggal {$laporan->tanggal} telah dibuat.");
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
