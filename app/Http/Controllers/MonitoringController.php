<?php

namespace App\Http\Controllers;

use App\Models\LogPemeriksaanWebsite;
use App\Models\Website;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    public function getMonitoringData(Request $request)
    {
        $query = LogPemeriksaanWebsite::with('website')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'success') {
                $query->where('berhasil', true);
            } elseif ($request->status === 'error') {
                $query->where('berhasil', false);
            }
        }

        // Get statistics
        $stats = $this->getStatistics($request);

        return response()->json([
            'data' => $query->get(),
            'stats' => $stats,
            'draw' => $request->draw,
            'recordsTotal' => LogPemeriksaanWebsite::count(),
            'recordsFiltered' => $query->count()
        ]);
    }

    private function getStatistics(Request $request)
    {
        $query = LogPemeriksaanWebsite::query();

        // Apply the same filters as main query
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'success') {
                $query->where('berhasil', true);
            } elseif ($request->status === 'error') {
                $query->where('berhasil', false);
            }
        }

        $total = $query->count();
        $online = $query->where('berhasil', true)->count();
        $error = $query->where('berhasil', false)->count();
        $avgResponse = $query->where('berhasil', true)->avg('waktu_respons');

        return [
            'total_websites' => Website::where('aktif', true)->count(),
            'online_websites' => $online,
            'error_websites' => $error,
            'avg_response' => $avgResponse ? round($avgResponse, 2) : 0
        ];
    }

    public function downloadPDF(Request $request)
    {
        $query = LogPemeriksaanWebsite::with('website')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('status') && $request->status) {
            if ($request->status === 'success') {
                $query->where('berhasil', true);
            } elseif ($request->status === 'error') {
                $query->where('berhasil', false);
            }
        }

        $data = $query->get();
        $stats = $this->getStatistics($request);

        $pdf = PDF::loadView('exports.monitoring-pdf', [
            'data' => $data,
            'stats' => $stats,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-monitoring-' . ($request->start_date ?? 'all') . '-to-' . ($request->end_date ?? 'all') . '.pdf';

        return $pdf->download($filename);
    }
}
