<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Laporan Monitoring Website - {{ $tanggal }}</title>
    <style>
        /* Reset CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .header p {
            font-size: 12px;
            color: #7f8c8d;
        }

        .summary {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .summary h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .summary-item {
            padding: 8px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .summary-item strong {
            display: block;
            font-size: 11px;
            color: #7f8c8d;
            margin-bottom: 3px;
        }

        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }

        th {
            background-color: #2c3e50;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #34495e;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .status-online {
            color: #27ae60;
            font-weight: bold;
        }

        .status-error {
            color: #e74c3c;
            font-weight: bold;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>LAPORAN MONITORING WEBSITE</h1>
        <p>Tanggal: {{ \App\Helpers\DateHelper::indonesianDate($tanggal) }}</p>
        <p>Dibuat pada: {{ \App\Helpers\DateHelper::indonesianDateTime(now()) }}</p>
    </div>

    <div class="summary">
        <h3>📊 RINGKASAN HASIL MONITORING</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <strong>Total Website Dimonitor</strong>
                <div class="summary-value">{{ $laporan->total_website }}</div>
            </div>
            <div class="summary-item">
                <strong>Website Online</strong>
                <div class="summary-value" style="color: #27ae60;">{{ $laporan->website_aktif }}</div>
            </div>
            <div class="summary-item">
                <strong>Website Error</strong>
                <div class="summary-value" style="color: #e74c3c;">{{ $laporan->website_error }}</div>
            </div>
            <div class="summary-item">
                <strong>Rata-rata Waktu Respons</strong>
                <div class="summary-value">{{ number_format($laporan->rata_rata_waktu_respons, 2) }} detik</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="20%">Website</th>
                <th width="10%" class="text-center">Status</th>
                <th width="15%" class="text-center">Waktu Respons</th>
                <th width="10%" class="text-center">Kode Status</th>
                <th width="30%">Pesan Error</th>
                <th width="15%" class="text-center">Waktu Pemeriksaan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $websiteId => $pemeriksaan)
            @php $lastCheck = $pemeriksaan->last(); @endphp
            @if($lastCheck && $lastCheck->website)
            <tr>
                <td>
                    <strong>{{ $lastCheck->website->nama_website }}</strong><br>
                    <small style="color: #7f8c8d;">{{ $lastCheck->website->url }}</small>
                </td>
                <td class="text-center">
                    <span class="{{ $lastCheck->berhasil ? 'status-online' : 'status-error' }}">
                        {{ $lastCheck->berhasil ? 'ONLINE' : 'ERROR' }}
                    </span>
                </td>
                <td class="text-center">
                    @if($lastCheck->waktu_respons > 0)
                    {{ number_format($lastCheck->waktu_respons, 2) }} detik
                    @else
                    <span style="color: #7f8c8d;">N/A</span>
                    @endif
                </td>
                <td class="text-center">
                    {{ $lastCheck->kode_status ?? 'N/A' }}
                </td>
                <td>
                    {{ $lastCheck->pesan_error ?? '-' }}
                </td>
                <td class="text-center">
                    {{ \App\Helpers\DateHelper::indonesianDateTime($lastCheck->created_at) }}
                </td>
            </tr>
            @endif
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px; color: #7f8c8d;">
                    Tidak ada data pemeriksaan untuk tanggal ini
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($data) && count($data) > 0)
    <div class="footer">
        <p>Laporan ini dibuat secara otomatis oleh Sistem Monitoring Website</p>
        <p>© {{ date('Y') }} - Monitoring System</p>
    </div>
    @endif
</body>

</html>