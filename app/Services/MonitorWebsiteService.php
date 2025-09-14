<?php

namespace App\Services;

use App\Models\LaporanHarian;
use App\Models\Website;
use App\Models\LogPemeriksaanWebsite;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\LaporanHarianExport;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Process\Process;
use Exception;
use Illuminate\Support\Facades\File;

class MonitorWebsiteService
{
    protected $client;
    protected $useScreenshot;
    protected $nodePath;
    protected $chromePath;
    protected $maxFileSize = 1024 * 1024; // 1MB dalam bytes

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 60,
            'connect_timeout' => 20,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);

        $this->useScreenshot = $this->checkScreenshotCapability();
        $this->setupEnvironmentPaths();
    }

    /**
     * Cek apakah environment mendukung screenshot
     */
    private function checkScreenshotCapability(): bool
    {
        // Selalu return true, kita handle error di method ambilScreenshot
        return true;
    }

    /**
     * Setup path environment untuk Node.js dan Chrome
     */
    private function setupEnvironmentPaths(): void
    {
        $this->nodePath = $this->findExecutablePath('node');
        $this->chromePath = $this->findChromePath();

        Log::info('Environment paths - Node: ' . ($this->nodePath ?? 'Not found') . ', Chrome: ' . ($this->chromePath ?? 'Not found'));
    }

    /**
     * Cari path executable
     */
    private function findExecutablePath(string $command): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows paths
            $paths = [
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe',
                trim(shell_exec('where node 2>NUL') ?? ''),
            ];
        } else {
            // Linux/Mac paths
            $paths = [
                '/usr/bin/node',
                '/usr/local/bin/node',
                '/opt/homebrew/bin/node',
                trim(shell_exec('which node 2>/dev/null') ?? ''),
            ];
        }

        foreach ($paths as $path) {
            if (!empty($path) && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Cari path Chrome/Chromium
     */
    private function findChromePath(): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows paths
            $paths = [
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                getenv('CHROME_PATH'),
            ];
        } else {
            // Linux/Mac paths
            $paths = [
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                getenv('CHROME_PATH'),
            ];
        }

        foreach ($paths as $path) {
            if (!empty($path) && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function periksaSemuaWebsite()
    {
        Log::info('Memulai pemeriksaan semua website');
        $websites = Website::where('aktif', true)->get();

        if ($websites->isEmpty()) {
            Log::info('Tidak ada website aktif untuk diperiksa');
            return;
        }

        Log::info('Memeriksa ' . $websites->count() . ' website');

        foreach ($websites as $website) {
            $this->periksaWebsiteTunggal($website);
            // Tunggu 12 detik antara pemeriksaan untuk menghindari overload
            sleep(12);
        }

        Log::info('Pemeriksaan semua website selesai');
    }

    public function periksaWebsiteTunggal(Website $website)
    {
        Log::info('Memeriksa website tunggal: ' . $website->url);

        $startTime = microtime(true);
        $logData = [
            'website_id' => $website->id,
            'waktu_respons' => 0,
            'berhasil' => false,
            'screenshot_path' => null,
            'pesan_error' => null
        ];

        try {
            $response = $this->client->get($website->url, [
                'on_stats' => function ($stats) use (&$logData) {
                    $logData['waktu_respons'] = $stats->getTransferTime();
                }
            ]);

            $endTime = microtime(true);
            $logData['waktu_respons'] = $endTime - $startTime;
            $logData['kode_status'] = $response->getStatusCode();
            $logData['berhasil'] = $response->getStatusCode() >= 200 && $response->getStatusCode() < 400;

            if ($logData['berhasil']) {
                // Hanya ambil screenshot jika environment mendukung
                if ($this->useScreenshot) {
                    $screenshotPath = $this->ambilScreenshotDenganRetry($website);
                    if ($screenshotPath) {
                        $logData['screenshot_path'] = $screenshotPath;
                        Log::info('Screenshot berhasil diambil untuk: ' . $website->url);
                    } else {
                        $logData['pesan_error'] = "Gagal mengambil screenshot setelah 3x percobaan";
                        Log::warning('Gagal mengambil screenshot untuk website: ' . $website->url);
                    }
                } else {
                    Log::info('Screenshot dinonaktifkan untuk environment ini');
                }
                Log::info('Website ' . $website->url . ' berhasil diakses. Status: ' . $logData['kode_status'] . ', Waktu: ' . round($logData['waktu_respons'], 2) . 's');
            } else {
                $logData['pesan_error'] = "Kode status: " . $response->getStatusCode();
                Log::warning('Website ' . $website->url . ' mengembalikan status error: ' . $logData['kode_status']);
            }
        } catch (RequestException $e) {
            $endTime = microtime(true);
            $logData['waktu_respons'] = $endTime - $startTime;
            $logData['berhasil'] = false;
            $logData['pesan_error'] = $this->formatErrorMessage($e);

            if ($e->hasResponse()) {
                $logData['kode_status'] = $e->getResponse()->getStatusCode();
            }

            Log::error('Website ' . $website->url . ' gagal diakses: ' . $logData['pesan_error'] . ', Waktu: ' . round($logData['waktu_respons'], 2) . 's');
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $logData['waktu_respons'] = $endTime - $startTime;
            $logData['berhasil'] = false;
            $logData['pesan_error'] = "Error: " . $e->getMessage();

            Log::error('Website ' . $website->url . ' error: ' . $e->getMessage() . ', Waktu: ' . round($logData['waktu_respons'], 2) . 's');
        }

        // Simpan data log
        $log = LogPemeriksaanWebsite::create($logData);

        // Update waktu respons dengan nilai yang akurat
        $log->update(['waktu_respons' => round($logData['waktu_respons'], 2)]);

        Log::info('Pemeriksaan website tunggal selesai: ' . $website->url);
        return true;
    }

    private function formatErrorMessage(RequestException $exception): string
    {
        if ($exception->getResponse()) {
            return "Error HTTP: " . $exception->getResponse()->getStatusCode() . " - " . $exception->getResponse()->getReasonPhrase();
        }
        return "Koneksi gagal: " . $exception->getMessage();
    }

    /**
     * Ambil screenshot dengan 3x percobaan untuk menghindari blank
     */
    private function ambilScreenshotDenganRetry(Website $website, $maxAttempts = 3): ?string
    {
        $attempt = 1;

        while ($attempt <= $maxAttempts) {
            Log::info("Percobaan screenshot ke-$attempt untuk: " . $website->url);

            $screenshotPath = $this->ambilScreenshot($website, $attempt);

            if ($screenshotPath && $this->isScreenshotValid($screenshotPath)) {
                Log::info("Screenshot valid ditemukan pada percobaan ke-$attempt");

                // Kompresi screenshot jika ukurannya terlalu besar
                $compressedPath = $this->kompresScreenshotJikaPerlu($screenshotPath);
                if ($compressedPath) {
                    return $compressedPath;
                }

                return $screenshotPath;
            }

            Log::warning("Screenshot tidak valid pada percobaan ke-$attempt");
            $attempt++;

            // Tunggu 2 detik sebelum percobaan berikutnya
            if ($attempt <= $maxAttempts) {
                sleep(2);
            }
        }

        Log::error("Gagal mengambil screenshot yang valid setelah $maxAttempts percobaan");
        return null;
    }

    /**
     * Cek apakah screenshot valid (tidak blank)
     */
    private function isScreenshotValid(string $filePath): bool
    {
        try {
            $fullPath = storage_path('app/public/' . $filePath);

            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                return false;
            }

            // Analisis gambar untuk mendeteksi apakah blank/putih
            $manager = new ImageManager(new Driver());
            $image = $manager->read($fullPath);

            // Ambil sample pixels dari berbagai area
            $width = $image->width();
            $height = $image->height();

            // Sample points: center, top-left, top-right, bottom-left, bottom-right
            $samplePoints = [
                [$width / 2, $height / 2], // center
                [50, 50], // top-left
                [$width - 50, 50], // top-right
                [50, $height - 50], // bottom-left
                [$width - 50, $height - 50] // bottom-right
            ];

            $totalColorDifference = 0;
            $sampleCount = 0;

            foreach ($samplePoints as $point) {
                if ($point[0] < $width && $point[1] < $height) {
                    $color = $image->pickColor((int)$point[0], (int)$point[1]);

                    // Untuk Intervention Image v3, color adalah object
                    // Convert ke integer value
                    $r = $color->red()->value();
                    $g = $color->green()->value();
                    $b = $color->blue()->value();

                    // Hitung perbedaan warna dari putih sempurna (255,255,255)
                    $colorDiff = abs($r - 255) + abs($g - 255) + abs($b - 255);
                    $totalColorDifference += $colorDiff;
                    $sampleCount++;
                }
            }

            if ($sampleCount === 0) {
                return false;
            }

            $averageDiff = $totalColorDifference / $sampleCount;

            // Jika average difference terlalu kecil, kemungkinan gambar blank/putih
            return $averageDiff > 50; // Threshold untuk mendeteksi non-blank image

        } catch (\Exception $e) {
            Log::warning('Error validating screenshot: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kompres screenshot jika ukurannya lebih dari 1MB
     */
    private function kompresScreenshotJikaPerlu(string $filePath): ?string
    {
        $fullPath = storage_path('app/public/' . $filePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        $fileSize = filesize($fullPath);

        // Jika file size kurang dari 1MB, tidak perlu kompresi
        if ($fileSize <= $this->maxFileSize) {
            Log::info("Screenshot ukuran normal: " . round($fileSize / 1024, 2) . " KB");
            return $filePath;
        }

        Log::info("Memulai kompresi screenshot: " . round($fileSize / 1024, 2) . " KB");

        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($fullPath);

            // Simpan file asli sebagai backup
            $backupPath = $fullPath . '.backup';
            copy($fullPath, $backupPath);

            // Kompresi dengan quality yang diturunkan secara bertahap
            $quality = 80;
            $attempt = 1;
            $maxAttempts = 5;

            while ($attempt <= $maxAttempts && filesize($fullPath) > $this->maxFileSize) {
                // Untuk PNG, kita perlu konversi ke JPEG untuk kompresi yang better
                if ($attempt >= 2) {
                    // Ubah extension ke jpg untuk attempt kedua dan seterusnya
                    $newPath = preg_replace('/\.png$/', '.jpg', $fullPath);
                    $image->toJpeg($quality)->save($newPath);

                    // Hapus file png lama
                    if ($newPath !== $fullPath && file_exists($fullPath)) {
                        unlink($fullPath);
                    }

                    $fullPath = $newPath;
                    $filePath = preg_replace('/\.png$/', '.jpg', $filePath);
                } else {
                    // Untuk PNG, coba optimize
                    $image->toPng()->save($fullPath);
                }

                // Kurangi quality untuk percobaan berikutnya
                $quality -= 15;
                $attempt++;

                // Baca ulang gambar untuk percobaan berikutnya
                if (file_exists($fullPath) && filesize($fullPath) > $this->maxFileSize && $attempt <= $maxAttempts) {
                    $image = $manager->read($fullPath);
                }
            }

            $newSize = filesize($fullPath);
            Log::info("Screenshot setelah kompresi: " . round($newSize / 1024, 2) . " KB (Pengurangan: " . round(($fileSize - $newSize) / 1024, 2) . " KB)");

            // Hapus backup file
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }

            return $filePath;
        } catch (\Exception $e) {
            Log::error('Gagal mengkompres screenshot: ' . $e->getMessage());

            // Restore backup jika ada error
            if (file_exists($backupPath) && file_exists($fullPath)) {
                copy($backupPath, $fullPath);
                unlink($backupPath);
            }

            return $filePath; // Return original path jika kompresi gagal
        }
    }

    private function ambilScreenshot(Website $website, int $attempt = 1): ?string
    {
        if (!$this->useScreenshot) {
            Log::info('Screenshot dinonaktifkan untuk environment ini');
            return null;
        }

        try {
            $directory = 'screenshots/' . $website->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $filename = $directory . '/' . now()->format('Y-m-d_H-i-s') . '_attempt_' . $attempt . '.png';
            $fullPath = storage_path('app/public/' . $filename);

            // Pastikan direktori tujuan ada
            $dirPath = dirname($fullPath);
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0755, true);
            }

            // Setup Browsershot untuk screenshot halaman penuh
            $browsershot = Browsershot::url($website->url)
                ->setOption('args', [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--headless=new',
                    '--disable-web-security',
                    '--hide-scrollbars'
                ])
                ->waitUntilNetworkIdle() // Tunggu sampai network idle
                ->dismissDialogs() // Tutup dialog yang mungkin muncul
                ->timeout(120) // Timeout 2 menit
                ->delay(5000) // Tunggu 5 detik untuk page load
                ->fullPage(); // Ambil seluruh halaman

            // Set binary paths jika ditemukan
            if ($this->nodePath) {
                $browsershot->setNodeBinary($this->nodePath);
            }

            if ($this->chromePath) {
                $browsershot->setChromePath($this->chromePath);
            }

            // Untuk attempt berbeda, gunakan viewport size yang berbeda
            switch ($attempt) {
                case 1:
                    $browsershot->windowSize(1920, 1080);
                    break;
                case 2:
                    $browsershot->windowSize(1366, 768);
                    break;
                case 3:
                    $browsershot->windowSize(1280, 720);
                    break;
                default:
                    $browsershot->windowSize(1920, 1080);
            }

            $browsershot->save($fullPath);

            // Verifikasi screenshot berhasil diambil
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                Log::error('Screenshot gagal dibuat atau file kosong: ' . $fullPath);
                return null;
            }

            // Tambahkan timestamp ke screenshot
            $this->tambahTimestampKeScreenshot($fullPath, $website);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Gagal mengambil screenshot untuk website: ' . $website->url . ' (Attempt: ' . $attempt . ')', [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function tambahTimestampKeScreenshot($imagePath, $website)
    {
        try {
            if (!file_exists($imagePath) || filesize($imagePath) === 0) {
                Log::warning('File screenshot tidak ditemukan atau kosong: ' . $imagePath);
                return;
            }

            $manager = new ImageManager(new Driver());
            $image = $manager->read($imagePath);

            $timestamp = now()->format('d-m-Y H:i:s');
            $text = "{$website->nama_website} | {$timestamp}";

            // Gunakan GD font built-in
            $image->text($text, 20, 30, function ($font) {
                $font->size(16);
                $font->color('#ff0000');
                $font->align('left');
                $font->valign('top');
            });

            $image->save($imagePath);
        } catch (\Exception $e) {
            Log::warning('Gagal menambahkan timestamp ke screenshot: ' . $e->getMessage());
        }
    }

    public function buatLaporanHarian($tanggal = null)
    {
        $tanggal = $tanggal ?: now()->format('Y-m-d');
        Log::info('Membuat laporan harian untuk tanggal: ' . $tanggal);

        $data = LogPemeriksaanWebsite::with('website')
            ->whereDate('created_at', $tanggal)
            ->get()
            ->groupBy('website_id');

        $totalWebsite = Website::where('aktif', true)->count();
        $websiteAktif = 0;
        $websiteError = 0;
        $totalWaktuRespons = 0;
        $jumlahPemeriksaan = 0;

        foreach ($data as $websiteId => $pemeriksaan) {
            $pemeriksaanTerakhir = $pemeriksaan->last();

            if ($pemeriksaanTerakhir) {
                if ($pemeriksaanTerakhir->berhasil) {
                    $websiteAktif++;
                } else {
                    $websiteError++;
                }

                if ($pemeriksaanTerakhir->waktu_respons > 0) {
                    $totalWaktuRespons += $pemeriksaanTerakhir->waktu_respons;
                    $jumlahPemeriksaan++;
                }
            }
        }

        $rataRataWaktuRespons = $jumlahPemeriksaan > 0 ? round($totalWaktuRespons / $jumlahPemeriksaan, 2) : 0;

        $laporan = LaporanHarian::updateOrCreate(
            ['tanggal' => $tanggal],
            [
                'total_website' => $totalWebsite,
                'website_aktif' => $websiteAktif,
                'website_error' => $websiteError,
                'rata_rata_waktu_respons' => $rataRataWaktuRespons
            ]
        );

        $this->generateLaporanExcel($laporan, $data, $tanggal);
        $this->generateLaporanPdf($laporan, $data, $tanggal);

        Log::info('Laporan harian berhasil dibuat untuk tanggal: ' . $tanggal);
        return $laporan;
    }

    private function generateLaporanExcel($laporan, $data, $tanggal)
    {
        try {
            if (!Storage::exists('laporan')) {
                Storage::makeDirectory('laporan');
            }

            $filename = 'laporan/laporan-harian-' . $tanggal . '.xlsx';

            // Pastikan kita menggunakan export yang benar
            $export = new \App\Exports\LaporanHarianExport($tanggal, $tanggal);

            // Simpan file Excel
            \Maatwebsite\Excel\Facades\Excel::store($export, $filename);

            $laporan->update(['path_xlsx' => $filename]);
            Log::info('Laporan Excel berhasil dibuat: ' . $filename);
        } catch (\Exception $e) {
            Log::error('Gagal membuat laporan Excel: ' . $e->getMessage());
            // Buat file Excel manual jika masih error
            $this->buatExcelManual($laporan, $data, $tanggal);
        }
    }

    // Fallback method jika Excel masih error
    private function buatExcelManual($laporan, $data, $tanggal)
    {
        try {
            $filename = 'laporan/laporan-harian-' . $tanggal . '.xlsx';
            $fullPath = storage_path('app/' . $filename);

            // Buat direktori jika belum ada
            if (!file_exists(dirname($fullPath))) {
                mkdir(dirname($fullPath), 0755, true);
            }

            // Simple CSV sebagai fallback
            $csvContent = "Tanggal,Total Website,Website Aktif,Website Error,Rata-rata Waktu Respons\n";
            $csvContent .= "{$tanggal},{$laporan->total_website},{$laporan->website_aktif},{$laporan->website_error},{$laporan->rata_rata_waktu_respons}\n";

            file_put_contents($fullPath, $csvContent);

            $laporan->update(['path_xlsx' => $filename]);
            Log::info('Laporan Excel manual berhasil dibuat: ' . $filename);
        } catch (\Exception $e) {
            Log::error('Gagal membuat laporan Excel manual: ' . $e->getMessage());
        }
    }

    private function generateLaporanPdf($laporan, $data, $tanggal)
    {
        try {
            if (!Storage::exists('laporan')) {
                Storage::makeDirectory('laporan');
            }

            $filename = 'laporan/laporan-harian-' . $tanggal . '.pdf';

            $pdf = PDF::loadView('exports.laporan-pdf', [
                'laporan' => $laporan,
                'data' => $data,
                'tanggal' => $tanggal // Pastikan ini dikirim
            ])->setPaper('a4', 'landscape');

            Storage::put($filename, $pdf->output());

            $laporan->update(['path_pdf' => $filename]);
            Log::info('Laporan PDF berhasil dibuat: ' . $filename);
        } catch (\Exception $e) {
            Log::error('Gagal membuat laporan PDF: ' . $e->getMessage());
        }
    }

    public function periksaWebsiteById($websiteId)
    {
        $website = Website::find($websiteId);
        if ($website) {
            return $this->periksaWebsiteTunggal($website);
        }

        Log::error('Website dengan ID ' . $websiteId . ' tidak ditemukan');
        return false;
    }

    public function periksaWebsiteManual()
    {
        $this->periksaSemuaWebsite();
        $this->buatLaporanHarian();
        return true;
    }

    /**
     * Method untuk mengecek status screenshot capability
     */
    public function canTakeScreenshots(): bool
    {
        return $this->useScreenshot;
    }
}
