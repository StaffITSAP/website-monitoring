<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    public static function indonesianDate($date)
    {
        $carbonDate = Carbon::parse($date);
        
        $days = [
            'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
        ];
        
        $months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $day = $days[$carbonDate->dayOfWeek];
        $date = $carbonDate->day;
        $month = $months[$carbonDate->month - 1];
        $year = $carbonDate->year;
        
        return "$day, $date $month $year";
    }

    public static function indonesianDateTime($date)
    {
        $carbonDate = Carbon::parse($date);
        
        $datePart = self::indonesianDate($date);
        $timePart = $carbonDate->format('H:i:s');
        
        return "$datePart $timePart";
    }
}