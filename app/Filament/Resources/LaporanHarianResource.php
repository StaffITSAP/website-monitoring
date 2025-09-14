<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanHarianResource\Pages;
use App\Filament\Resources\LaporanHarianResource\RelationManagers;
use App\Models\LaporanHarian;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Exports\LaporanHarianExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class LaporanHarianResource extends Resource
{
    protected static ?string $model = LaporanHarian::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Laporan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('tanggal')
                    ->required(),
                Forms\Components\TextInput::make('total_website')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('website_aktif')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('website_error')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('rata_rata_waktu_respons')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('path_xlsx')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('path_pdf')
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i:s') // contoh: 14 Sep 2025 13:45:12
                    ->label('Waktu Pemeriksaan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_website')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('website_aktif')
                    ->numeric()
                    ->sortable()
                    ->color('success'),
                Tables\Columns\TextColumn::make('website_error')
                    ->numeric()
                    ->sortable()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('rata_rata_waktu_respons')
                    ->numeric()
                    ->suffix(' detik')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_mulai'),
                        Forms\Components\DatePicker::make('tanggal_selesai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_mulai'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_selesai'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('unduh_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(
                        fn(LaporanHarian $record): bool =>
                        $record->path_xlsx && Storage::exists($record->path_xlsx)
                    )
                    ->action(function (LaporanHarian $record) {
                        if ($record->path_xlsx && Storage::exists($record->path_xlsx)) {
                            return response()->download(storage_path('app/' . $record->path_xlsx));
                        }
                    }),
                Tables\Actions\Action::make('unduh_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(
                        fn(LaporanHarian $record): bool =>
                        $record->path_pdf && Storage::exists($record->path_pdf)
                    )
                    ->action(function (LaporanHarian $record) {
                        if ($record->path_pdf && Storage::exists($record->path_pdf)) {
                            return response()->download(storage_path('app/' . $record->path_pdf));
                        }
                    }),
                Tables\Actions\Action::make('buat_ulang_laporan')
                    ->label('Buat Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (LaporanHarian $record) {
                        // Panggil service untuk buat laporan ulang
                        $service = app(\App\Services\MonitorWebsiteService::class);
                        $service->buatLaporanHarian($record->tanggal);

                        // Refresh data
                        return redirect()->refresh();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_excel')
                        ->label('Export Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $tanggalMulai = $records->min('tanggal');
                            $tanggalSelesai = $records->max('tanggal');

                            return Excel::download(
                                new LaporanHarianExport($tanggalMulai, $tanggalSelesai),
                                'laporan-monitoring-' . $tanggalMulai . '-hingga-' . $tanggalSelesai . '.xlsx'
                            );
                        }),
                    Tables\Actions\BulkAction::make('export_pdf')
                        ->label('Export PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $tanggalMulai = $records->min('tanggal');
                            $tanggalSelesai = $records->max('tanggal');
                            $laporan = LaporanHarian::whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])->get();

                            $pdf = PDF::loadView('exports.laporan-pdf', [
                                'laporan' => $laporan,
                                'tanggalMulai' => $tanggalMulai,
                                'tanggalSelesai' => $tanggalSelesai
                            ]);

                            return $pdf->download('laporan-monitoring-' . $tanggalMulai . '-hingga-' . $tanggalSelesai . '.pdf');
                        }),
                    Tables\Actions\BulkAction::make('buat_ulang_semua')
                        ->label('Buat Ulang Semua')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records) {
                            $service = app(\App\Services\MonitorWebsiteService::class);

                            foreach ($records as $record) {
                                $service->buatLaporanHarian($record->tanggal);
                            }

                            return redirect()->refresh();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLaporanHarians::route('/'),
            'create' => Pages\CreateLaporanHarian::route('/create'),
            'edit' => Pages\EditLaporanHarian::route('/{record}/edit'),
        ];
    }
}
