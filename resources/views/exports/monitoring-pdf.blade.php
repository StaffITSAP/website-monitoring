<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Monitoring Website</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            margin: 15px;
            font-size: 10px;
            line-height: 1.2;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2c3e50;
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .header p {
            margin: 2px 0;
            color: #666;
            font-size: 9px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8px;
        }

        .data-table th {
            background: #2c3e50;
            color: white;
            padding: 6px;
            text-align: left;
            font-weight: bold;
        }

        .data-table td {
            padding: 5px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .status-online {
            color: #28a745;
            font-weight: bold;
        }

        .status-error {
            color: #dc3545;
            font-weight: bold;
        }

        .date-title {
            text-align: center;
            font-weight: bold;
            margin: 20px 0 10px 0;
            font-size: 12px;
            background-color: #f0f0f0;
            padding: 5px;
            border-radius: 4px;
        }

        table.screenshot-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        table.screenshot-table td {
            width: 50%;
            text-align: center;
            border: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
            height: 180px;
        }

        .screenshot-image {
            width: 25%;
            max-height: 100px;
            border: 1px solid #ddd;
            margin-bottom: 5px;
            object-fit: contain;
        }

        .screenshot-website {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .screenshot-date {
            font-size: 9px;
            color: #666;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 7px;
            color: #999;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 6px;
            margin: 10px 0;
        }

        .section-title {
            background: #f8f9fa;
            padding: 8px;
            border-left: 4px solid #667eea;
            margin: 15px 0 10px 0;
            font-weight: bold;
            color: #2c3e50;
            font-size: 11px;
        }

        .empty-cell {
            border: none !important;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1>📊 LAPORAN MONITORING WEBSITE</h1>
        <p>Periode: {{ $start_date ? \Carbon\Carbon::parse($start_date)->format('d M Y') : 'Semua Data' }} -
            {{ $end_date ? \Carbon\Carbon::parse($end_date)->format('d M Y') : 'Semua Data' }}
        </p>
        <p>Dibuat pada: {{ now()->format('d M Y H:i:s') }}</p>
    </div>

    <!-- Data Monitoring -->
    <div class="section-title">📋 DATA HASIL MONITORING</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="25%">Website</th>
                <th width="10%">Status</th>
                <th width="12%">Waktu Respons</th>
                <th width="10%">Kode Status</th>
                <th width="28%">Pesan Error</th>
                <th width="15%">Waktu Pemeriksaan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $log)
            <tr>
                <td>
                    <div class="website-info">{{ $log->website->nama_website }}</div>
                    <div class="website-url">{{ $log->website->url }}</div>
                </td>
                <td class="{{ $log->berhasil ? 'status-online' : 'status-error' }}">
                    {{ $log->berhasil ? 'ONLINE' : 'ERROR' }}
                </td>
                <td>
                    {{ $log->waktu_respons ? number_format($log->waktu_respons, 2) . 's' : 'N/A' }}
                </td>
                <td>{{ $log->kode_status ?? 'N/A' }}</td>
                <td>{{ $log->pesan_error ? substr($log->pesan_error, 0, 50) . (strlen($log->pesan_error) > 50 ? '...' : '') : '-' }}</td>
                <td>{{ $log->created_at->format('d M Y H:i') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="no-data">Tidak ada data monitoring untuk periode ini</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Screenshot Section -->
    <div class="page-break"></div>

    @php
    $groupedScreenshots = $data->where('screenshot_path')->filter(function($log) {
        return $log->screenshot_path && file_exists(storage_path('app/public/' . $log->screenshot_path));
    })->groupBy(function($item) {
        return \Carbon\Carbon::parse($item->created_at)->format('d-m-Y');
    });
    @endphp

    @foreach($groupedScreenshots as $date => $screenshots)
        <div class="date-title">Screenshot Tanggal: {{ $date }}</div>

        @php
            $chunks = $screenshots->chunk(4);
        @endphp

        @foreach($chunks as $chunkIndex => $chunk)
            <table class="screenshot-table">
                @foreach($chunk->chunk(2) as $rowIndex => $row)
                    <tr>
                        @foreach($row as $log)
                            <td>
                                <img src="{{ public_path('storage/' . $log->screenshot_path) }}" class="screenshot-image">
                                <div class="screenshot-website">{{ $log->website->nama_website }}</div>
                                <div class="screenshot-date">{{ $log->created_at->format('d M Y H:i') }}</div>
                            </td>
                        @endforeach
                        
                        {{-- Tambahkan sel kosong jika baris tidak penuh --}}
                        @for($i = $row->count(); $i < 2; $i++)
                            <td class="empty-cell"></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
            
            {{-- Hanya tambahkan page break jika bukan chunk terakhir --}}
            @if(!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach

        {{-- Hanya tambahkan page break jika bukan tanggal terakhir --}}
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    @if($groupedScreenshots->isEmpty())
        <div class="section-title">🖼️ SCREENSHOT WEBSITE</div>
        <div class="no-data">
            <p>Tidak ada screenshot yang tersedia untuk periode ini</p>
        </div>
    @endif

    <div class="footer">
        <p>Laporan ini dibuat otomatis oleh Staff IT Solusi Arya Prima | {{ now()->format('d M Y H:i:s') }}</p>
    </div>
</body>
</html>