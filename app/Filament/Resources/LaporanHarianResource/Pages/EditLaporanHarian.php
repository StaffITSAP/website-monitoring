<?php

namespace App\Filament\Resources\LaporanHarianResource\Pages;

use App\Filament\Resources\LaporanHarianResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLaporanHarian extends EditRecord
{
    protected static string $resource = LaporanHarianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
