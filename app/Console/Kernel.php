<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\PeriksaWebsiteCommand::class,
        Commands\BuatLaporanHarianCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Jalankan pemeriksaan website setiap 5 menit
        $schedule->command('website:periksa')->everyFiveMinutes();

        // Jalankan pembuatan laporan harian setiap hari jam 09:00
        $schedule->command('laporan:harian')->dailyAt('09:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
