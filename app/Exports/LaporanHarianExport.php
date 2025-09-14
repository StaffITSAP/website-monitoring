<?php

namespace App\Exports;

use App\Models\LaporanHarian;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanHarianExport implements FromCollection, WithHeadings, WithTitle, WithMapping, WithStyles
{
    protected $tanggalMulai;
    protected $tanggalSelesai;

    public function __construct($tanggalMulai, $tanggalSelesai)
    {
        $this->tanggalMulai = $tanggalMulai;
        $this->tanggalSelesai = $tanggalSelesai;
    }

    public function collection()
    {
        return LaporanHarian::whereBetween('tanggal', [$this->tanggalMulai, $this->tanggalSelesai])->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Total Website',
            'Website Aktif',
            'Website Error',
            'Rata-rata Waktu Respons (detik)'
        ];
    }

    public function map($laporan): array
    {
        return [
            $laporan->tanggal->format('d F Y'),
            $laporan->total_website,
            $laporan->website_aktif,
            $laporan->website_error,
            number_format($laporan->rata_rata_waktu_respons, 2)
        ];
    }

    public function title(): string
    {
        return 'Laporan Monitoring Website';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['argb' => 'FFE0E0E0']
                ]
            ],
        ];
    }
}
