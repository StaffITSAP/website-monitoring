<?php

namespace App\Filament\Resources\LaporanHarianResource\Pages;

use App\Filament\Resources\LaporanHarianResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLaporanHarians extends ListRecords
{
    protected static string $resource = LaporanHarianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
