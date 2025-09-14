<?php

namespace App\Filament\Resources\LogPemeriksaanWebsiteResource\Pages;

use App\Filament\Resources\LogPemeriksaanWebsiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLogPemeriksaanWebsites extends ListRecords
{
    protected static string $resource = LogPemeriksaanWebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
