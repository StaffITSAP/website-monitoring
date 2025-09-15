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
use Exception;

class MonitorWebsiteService
{
    protected $client;
    protected $useScreenshot = true;
    protected $nodePath;
    protected $chromePath;

    // Limit ukuran screenshot (3 MB)
    protected $maxFileSize = 3 * 1024 * 1024;

    // Jeda antar situs
    protected $siteDelaySeconds = 20;

    // Retry HTTP
    protected $httpMaxAttempts = 3;
    protected $httpBaseDelaySeconds = 5; // 5s -> 10s -> 20s

    // Retry Screenshot
    protected $screenshotMaxAttempts = 3;
    protected $screenshotBaseDelaySeconds = 4; // 4s -> 8s

    // Puppeteer timeouts (ms)
    protected $puppeteerProtocolTimeout = 240000; // 240s
    protected $puppeteerGotoTimeout     = 200000; // 200s

    public function __construct()
    {
        @ini_set('memory_limit', '512M');

        $this->client = new Client([
            // Perpanjang biar tidak cepat cURL 28
            'timeout'         => 120,
            'connect_timeout' => 60,
            'verify'          => false,
            'allow_redirects' => [
                'max'             => 10,
                'strict'          => false,
                'referer'         => true,
                'protocols'       => ['http','https'],
                'track_redirects' => true,
            ],
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);

        $this->setupEnvironmentPaths();
    }

    private function setupEnvironmentPaths(): void
    {
        $this->nodePath   = $this->findExecutablePath('node');
        $this->chromePath = $this->findChromePath();
        Log::info('Environment paths - Node: ' . ($this->nodePath ?? 'Not found') . ', Chrome: ' . ($this->chromePath ?? 'Not found'));
    }

    private function findExecutablePath(string $command): ?string
    {
        if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
            $paths = [
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe',
                trim(@shell_exec('where node 2>NUL') ?? ''),
            ];
        } else {
            $paths = [
                '/usr/bin/node',
                '/usr/local/bin/node',
                '/opt/homebrew/bin/node',
                trim(@shell_exec('which node 2>/dev/null') ?? ''),
            ];
        }
        foreach ($paths as $p) if(!empty($p) && file_exists($p)) return $p;
        return null;
    }

    private function findChromePath(): ?string
    {
        if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
            $paths = [
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                getenv('CHROME_PATH'),
            ];
        } else {
            $paths = [
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                '/bin/google-chrome-stable',
                getenv('CHROME_PATH'),
            ];
        }
        foreach ($paths as $p) if(!empty($p) && file_exists($p)) return $p;
        return null;
    }

    public function periksaSemuaWebsite()
    {
        Log::info('Memulai pemeriksaan semua website');
        $websites = Website::where('aktif', true)->orderBy('id')->get();

        if ($websites->isEmpty()) {
            Log::info('Tidak ada website aktif untuk diperiksa');
            return;
        }

        Log::info('Memeriksa ' . $websites->count() . ' website');

        foreach ($websites as $website) {
            try {
                $this->periksaWebsiteTunggal($website);
            } catch (Exception $e) {
                // Jangan biarkan 1 situs stop seluruh loop
                Log::error('Fatal saat memeriksa website ID '.$website->id.': '.$e->getMessage());
            } finally {
                sleep($this->siteDelaySeconds);
            }
        }

        Log::info('Pemeriksaan semua website selesai');
    }

    public function periksaWebsiteTunggal(Website $website)
    {
        Log::info('Memeriksa website tunggal: ' . $website->url);

        $startTime = microtime(true);
        $logData = [
            'website_id'      => $website->id,
            'waktu_respons'   => 0,
            'berhasil'        => false,
            'screenshot_path' => null,
            'pesan_error'     => null,
            'kode_status'     => null,
        ];

        $response = null;
        $lastException = null;

        // ==== 1) Coba HTTP GET dengan retry ====
        $attempt = 1;
        while ($attempt <= $this->httpMaxAttempts) {
            $attemptStart = microtime(true);
            try {
                $response = $this->client->get($website->url, [
                    'http_errors' => false,
                    'on_stats' => function ($stats) use (&$logData) {
                        $logData['waktu_respons'] = $stats->getTransferTime();
                    }
                ]);
                $logData['kode_status'] = $response->getStatusCode();
                break; // keluar retry loop
            } catch (RequestException $e) {
                $lastException = $e;
                $logData['pesan_error'] = $this->formatErrorMessage($e);
                $delay = $this->httpBaseDelaySeconds * (2 ** ($attempt - 1));
                Log::warning("Percobaan HTTP ke-{$attempt} gagal untuk {$website->url}: {$logData['pesan_error']}. Backoff {$delay}s");
                sleep($delay);
                $attempt++;
            } catch (Exception $e) {
                $lastException = $e;
                $logData['pesan_error'] = "Error: " . $e->getMessage();
                $delay = $this->httpBaseDelaySeconds * (2 ** ($attempt - 1));
                Log::warning("Percobaan HTTP ke-{$attempt} exception untuk {$website->url}: {$logData['pesan_error']}. Backoff {$delay}s");
                sleep($delay);
                $attempt++;
            } finally {
                $attemptEnd = microtime(true);
                $logData['waktu_respons'] = max($logData['waktu_respons'], round($attemptEnd - $attemptStart, 2));
            }
        }

        // Fallback jika on_stats tidak jalan
        $endTime = microtime(true);
        if (empty($logData['waktu_respons']) || $logData['waktu_respons'] <= 0) {
            $logData['waktu_respons'] = round($endTime - $startTime, 2);
        }

        // Tentukan status berhasil berdasarkan HTTP status (200–399)
        if ($response) {
            $status = $response->getStatusCode();
            $logData['berhasil'] = ($status >= 200 && $status < 400);
            if (!$logData['berhasil']) {
                $logData['pesan_error'] = "Kode status: " . $status;
            }
        } else {
            $logData['berhasil']    = false;
            $logData['pesan_error'] = $logData['pesan_error'] ?? ($lastException ? $lastException->getMessage() : 'Tidak ada response');
        }

        // ==== 2) SELALU AMBIL SCREENSHOT (bahkan jika HTTP gagal/timeout) ====
        if ($this->useScreenshot) {
            $shot = $this->ambilScreenshotDenganRetry($website, $this->screenshotMaxAttempts);
            if ($shot) {
                $logData['screenshot_path'] = $shot;
                Log::info('Screenshot berhasil diambil untuk: ' . $website->url);
            } else {
                Log::warning('Gagal mengambil screenshot untuk website: ' . $website->url);
            }
        }

        // ==== 3) Simpan log ====
        $log = LogPemeriksaanWebsite::create($logData);
        $log->update(['waktu_respons' => round($logData['waktu_respons'], 2)]);

        Log::info('Website ' . $website->url . ' ' . ($logData['berhasil'] ? 'OK' : 'ERROR') . '. Status: ' . ($logData['kode_status'] ?? 'N/A') . ', Waktu: ' . round($logData['waktu_respons'], 2) . 's');
        Log::info('Pemeriksaan website tunggal selesai: ' . $website->url);

        return true;
    }

    private function formatErrorMessage(RequestException $exception): string
    {
        if ($exception->getHandlerContext()['errno'] ?? null) {
            $errno = $exception->getHandlerContext()['errno'];
            $error = $exception->getHandlerContext()['error'] ?? '';
            return "cURL error {$errno}: {$error}";
        }
        if ($exception->getResponse()) {
            return "Error HTTP: " . $exception->getResponse()->getStatusCode() . " - " . $exception->getResponse()->getReasonPhrase();
        }
        return "Koneksi gagal: " . $exception->getMessage();
    }

    /**
     * Retry screenshot dengan strategi waitUntil adaptif:
     * Attempt-1: 'load' → Attempt-2: 'domcontentloaded' → Attempt-3: 'networkidle0'
     */
    private function ambilScreenshotDenganRetry(Website $website, int $maxAttempts = 3): ?string
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            Log::info("Percobaan screenshot ke-{$attempt} untuk: " . $website->url);

            $path = $this->ambilScreenshot($website, $attempt);

            if ($path && $this->isScreenshotValid($path)) {
                Log::info("Screenshot valid pada percobaan ke-{$attempt}");
                $compressed = $this->kompresScreenshotJikaPerlu($path);
                return $compressed ?: $path;
            }

            Log::warning("Screenshot tidak valid pada percobaan ke-{$attempt}");

            if ($attempt < $maxAttempts) {
                $delay = max($this->screenshotBaseDelaySeconds, $this->screenshotBaseDelaySeconds * (2 ** ($attempt - 1)));
                sleep($delay);
            }
        }

        Log::error("Gagal mengambil screenshot yang valid setelah {$maxAttempts} percobaan");
        return null;
    }

    private function isScreenshotValid(string $filePath): bool
    {
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            if (!file_exists($fullPath) || filesize($fullPath) === 0) return false;

            $manager = new ImageManager(new Driver());
            $image   = $manager->read($fullPath);

            $w = $image->width();
            $h = $image->height();

            $pts = [[$w/2,$h/2],[20,20],[$w-20,20],[20,$h-20],[$w-20,$h-20]];
            $diff=0;$n=0;
            foreach ($pts as $p) {
                $x=(int)max(0,min($w-1,$p[0])); $y=(int)max(0,min($h-1,$p[1]));
                $c=$image->pickColor($x,$y);
                $r=$c->red()->value(); $g=$c->green()->value(); $b=$c->blue()->value();
                $diff += abs($r-255)+abs($g-255)+abs($b-255); $n++;
            }
            if ($n===0) return false;
            return ($diff/$n) > 50;
        } catch (Exception $e) {
            Log::warning('Error validating screenshot: ' . $e->getMessage());
            return false;
        }
    }

    private function kompresScreenshotJikaPerlu(string $filePath): ?string
    {
        $fullPath = storage_path('app/public/' . $filePath);
        if (!file_exists($fullPath)) return null;

        $size = filesize($fullPath);
        if ($size <= $this->maxFileSize) {
            Log::info("Screenshot ukuran normal: " . round($size/1024,2) . " KB");
            return $filePath;
        }

        Log::info("Memulai kompresi screenshot: " . round($size/1024,2) . " KB");

        $backup = $fullPath.'.backup';
        @copy($fullPath, $backup);

        try {
            $manager = new ImageManager(new Driver());
            $image   = $manager->read($fullPath);

            $q=70; $min=25; $step=10;

            if (!preg_match('/\.jpe?g$/i',$fullPath)) {
                $newFull = preg_replace('/\.(png|webp)$/i','.jpg',$fullPath) ?: ($fullPath.'.jpg');
                $image->toJpeg($q)->save($newFull);
                @unlink($fullPath);
                $fullPath = $newFull;
                $filePath = preg_replace('/\.(png|webp)$/i','.jpg',$filePath) ?: ($filePath.'.jpg');
            }

            while (filesize($fullPath) > $this->maxFileSize && $q > $min) {
                $q = max($min, $q-$step);
                $image = $manager->read($fullPath);
                $image->toJpeg($q)->save($fullPath);
            }

            @unlink($backup);
            return $filePath;

        } catch (Exception $e) {
            Log::error('Gagal mengkompres screenshot: '.$e->getMessage());
            if (file_exists($backup)) { @copy($backup,$fullPath); @unlink($backup); }
            return $filePath;
        }
    }

    private function ambilScreenshot(Website $website, int $attempt = 1): ?string
    {
        if (!$this->useScreenshot) return null;

        try {
            $directory = 'screenshots/' . $website->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $filename = $directory.'/'.now()->format('Y-m-d_H-i-s')."_attempt_{$attempt}.jpg";
            $fullPath = storage_path('app/public/'.$filename);
            if (!is_dir(dirname($fullPath))) @mkdir(dirname($fullPath),0755,true);

            // WAIT-UNTIL adaptif
            $waitUntil = match ($attempt) {
                1 => 'load',
                2 => 'domcontentloaded',
                default => 'networkidle0',
            };

            $delayMs = match ($attempt) {
                1 => 6000,
                2 => 10000,
                default => 15000,
            };

            [$vw,$vh] = match ($attempt) {
                1 => [1920,1080],
                2 => [1366,768],
                default => [1280,720],
            };

            $browsershot = Browsershot::url($website->url)
                ->setOption('args', [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--headless=new',
                    '--disable-web-security',
                    '--hide-scrollbars',
                ])
                ->setOption('protocolTimeout', $this->puppeteerProtocolTimeout)
                ->setOption('timeout',         $this->puppeteerGotoTimeout)
                ->setOption('waitUntil',       $waitUntil)
                ->dismissDialogs()
                ->timeout((int)ceil($this->puppeteerProtocolTimeout/1000))
                ->delay($delayMs)
                ->windowSize($vw,$vh)
                ->fullPage()
                ->quality(25)
                ->setScreenshotType('jpeg');

            if ($this->nodePath)   $browsershot->setNodeBinary($this->nodePath);
            if ($this->chromePath) $browsershot->setChromePath($this->chromePath);

            $browsershot->save($fullPath);

            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                Log::error('Screenshot gagal dibuat atau file kosong: ' . $fullPath);
                return null;
            }

            $this->tambahTimestampKeScreenshot($fullPath, $website);
            return $filename;

        } catch (Exception $e) {
            Log::error('Gagal mengambil screenshot untuk '.$website->url." (Attempt: {$attempt})", ['error'=>$e->getMessage()]);
            return null;
        }
    }

    private function tambahTimestampKeScreenshot($imagePath, $website)
    {
        try {
            if (!file_exists($imagePath) || filesize($imagePath) === 0) return;

            $manager = new ImageManager(new Driver());
            $image   = $manager->read($imagePath);

            $timestamp = now()->format('d-m-Y H:i:s');
            $text      = "{$website->nama_website} | {$timestamp}";

            $image->text($text, 20, 30, function ($font) {
                $font->size(18);
                $font->color('#ff0000');
                $font->align('left');
                $font->valign('top');
            });

            $image->save($imagePath);
        } catch (Exception $e) {
            Log::warning('Gagal menambahkan timestamp: '.$e->getMessage());
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

        foreach ($data as $pemeriksaan) {
            $terakhir = $pemeriksaan->last();
            if ($terakhir) {
                $terakhir->berhasil ? $websiteAktif++ : $websiteError++;
                if ($terakhir->waktu_respons > 0) {
                    $totalWaktuRespons += $terakhir->waktu_respons;
                    $jumlahPemeriksaan++;
                }
            }
        }

        $rata2 = $jumlahPemeriksaan > 0 ? round($totalWaktuRespons / $jumlahPemeriksaan, 2) : 0;

        $laporan = LaporanHarian::updateOrCreate(
            ['tanggal' => $tanggal],
            [
                'total_website' => $totalWebsite,
                'website_aktif' => $websiteAktif,
                'website_error' => $websiteError,
                'rata_rata_waktu_respons' => $rata2
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
            if (!Storage::exists('laporan')) Storage::makeDirectory('laporan');
            $filename = 'laporan/laporan-harian-' . $tanggal . '.xlsx';
            $export = new \App\Exports\LaporanHarianExport($tanggal, $tanggal);
            \Maatwebsite\Excel\Facades\Excel::store($export, $filename);
            $laporan->update(['path_xlsx' => $filename]);
            Log::info('Laporan Excel berhasil dibuat: ' . $filename);
        } catch (Exception $e) {
            Log::error('Gagal membuat laporan Excel: ' . $e->getMessage());
            $this->buatExcelManual($laporan, $data, $tanggal);
        }
    }

    private function buatExcelManual($laporan, $data, $tanggal)
    {
        try {
            $filename = 'laporan/laporan-harian-' . $tanggal . '.xlsx';
            $fullPath = storage_path('app/' . $filename);
            if (!is_dir(dirname($fullPath))) @mkdir(dirname($fullPath),0755,true);

            $csvContent = "Tanggal,Total Website,Website Aktif,Website Error,Rata-rata Waktu Respons\n";
            $csvContent .= "{$tanggal},{$laporan->total_website},{$laporan->website_aktif},{$laporan->website_error},{$laporan->rata_rata_waktu_respons}\n";

            file_put_contents($fullPath, $csvContent);
            $laporan->update(['path_xlsx' => $filename]);
            Log::info('Laporan Excel manual berhasil dibuat: ' . $filename);
        } catch (Exception $e) {
            Log::error('Gagal membuat laporan Excel manual: ' . $e->getMessage());
        }
    }

    private function generateLaporanPdf($laporan, $data, $tanggal)
    {
        try {
            if (!Storage::exists('laporan')) Storage::makeDirectory('laporan');
            $filename = 'laporan/laporan-harian-' . $tanggal . '.pdf';
            $pdf = Pdf::loadView('exports.laporan-pdf', compact('laporan','data','tanggal'))
                ->setPaper('a4', 'landscape');
            Storage::put($filename, $pdf->output());
            $laporan->update(['path_pdf' => $filename]);
            Log::info('Laporan PDF berhasil dibuat: ' . $filename);
        } catch (Exception $e) {
            Log::error('Gagal membuat laporan PDF: ' . $e->getMessage());
        }
    }

    public function periksaWebsiteById($websiteId)
    {
        $website = Website::find($websiteId);
        if ($website) return $this->periksaWebsiteTunggal($website);
        Log::error('Website dengan ID ' . $websiteId . ' tidak ditemukan');
        return false;
    }

    public function periksaWebsiteManual()
    {
        $this->periksaSemuaWebsite();
        $this->buatLaporanHarian();
        return true;
    }

    public function canTakeScreenshots(): bool
    {
        return $this->useScreenshot;
    }
}
