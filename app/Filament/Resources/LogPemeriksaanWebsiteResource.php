<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LogPemeriksaanWebsiteResource\Pages;
use App\Models\LogPemeriksaanWebsite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Enums\ActionsPosition;


class LogPemeriksaanWebsiteResource extends Resource
{
    protected static ?string $model = LogPemeriksaanWebsite::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $modelLabel = 'Log Pemeriksaan';
    protected static ?string $pluralModelLabel = 'Log Pemeriksaan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('website_id')
                    ->relationship('website', 'nama_website')
                    ->required()
                    ->label('Website'),

                Forms\Components\TextInput::make('kode_status')
                    ->numeric()
                    ->nullable()
                    ->label('Kode Status HTTP'),

                Forms\Components\TextInput::make('waktu_respons')
                    ->numeric()
                    ->suffix('detik')
                    ->nullable()
                    ->label('Waktu Respons'),

                Forms\Components\Toggle::make('berhasil')
                    ->required()
                    ->label('Berhasil Diakses'),

                Forms\Components\Textarea::make('pesan_error')
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->nullable()
                    ->label('Pesan Error'),

                Forms\Components\TextInput::make('screenshot_path')
                    ->maxLength(255)
                    ->nullable()
                    ->label('Path Screenshot'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('website.nama_website')
                    ->sortable()
                    ->searchable()
                    ->label('Website'),

                Tables\Columns\TextColumn::make('kode_status')
                    ->numeric()
                    ->sortable()
                    ->color(fn($record) => $record->berhasil ? 'success' : 'danger')
                    ->label('Kode Status'),

                Tables\Columns\TextColumn::make('waktu_respons')
                    ->numeric()
                    ->suffix(' detik')
                    ->sortable()
                    ->label('Waktu Respons')
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 2) : 'N/A'),

                Tables\Columns\IconColumn::make('berhasil')
                    ->boolean()
                    ->label('Berhasil'),

                Tables\Columns\ImageColumn::make('screenshot_path')
                    ->label('Screenshot')
                    ->getStateUsing(function ($record) {
                        if ($record->screenshot_path && Storage::disk('public')->exists($record->screenshot_path)) {
                            return Storage::disk('public')->url($record->screenshot_path);
                        }
                        return null;
                    })
                    ->extraImgAttributes([
                        'style' => 'max-width: 100px; max-height: 60px; object-fit: cover; border: 1px solid #e5e7eb; border-radius: 4px;'
                    ])
                    ->defaultImageUrl(url('/images/no-screenshot.png'))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i:s') // contoh: 14 Sep 2025 13:45:12
                    ->label('Waktu Pemeriksaan')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pesan_error')
                    ->limit(30)
                    ->label('Pesan Error')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('berhasil')
                    ->label('Hanya yang berhasil')
                    ->query(fn(Builder $query) => $query->where('berhasil', true)),

                Tables\Filters\Filter::make('gagal')
                    ->label('Hanya yang gagal')
                    ->query(fn(Builder $query) => $query->where('berhasil', false)),

                Tables\Filters\SelectFilter::make('website_id')
                    ->relationship('website', 'nama_website')
                    ->label('Filter by Website'),

                Tables\Filters\Filter::make('has_screenshot')
                    ->label('Dengan Screenshot')
                    ->query(fn(Builder $query) => $query->whereNotNull('screenshot_path')->where('screenshot_path', '!=', '')),

                Tables\Filters\Filter::make('no_screenshot')
                    ->label('Tanpa Screenshot')
                    ->query(fn(Builder $query) => $query->whereNull('screenshot_path')->orWhere('screenshot_path', '')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('lihat_screenshot')
                        ->label('Lihat Screenshot')
                        ->icon('heroicon-o-photo')
                        ->url(fn($record) => $record->screenshot_path && Storage::disk('public')->exists($record->screenshot_path)
                            ? Storage::disk('public')->url($record->screenshot_path)
                            : null)
                        ->openUrlInNewTab()
                        ->hidden(fn($record) => empty($record->screenshot_path) || !Storage::disk('public')->exists($record->screenshot_path)),

                    Tables\Actions\Action::make('periksa_ulang')
                        ->label('Periksa Ulang')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($record) {
                            try {
                                $service = app(\App\Services\MonitorWebsiteService::class);
                                $service->periksaWebsiteTunggal($record->website);

                                \Filament\Notifications\Notification::make()
                                    ->title('Pemeriksaan Ulang Berhasil')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Pemeriksaan Ulang Gagal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Aksi')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->actionsPosition(ActionsPosition::BeforeColumns) // posisi aksi di kolom paling kiri
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada data pemeriksaan')
            ->emptyStateDescription('Jadwalkan pemeriksaan website untuk mulai mengumpulkan data.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }


    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogPemeriksaanWebsites::route('/'),
            'create' => Pages\CreateLogPemeriksaanWebsite::route('/create'),
            'edit' => Pages\EditLogPemeriksaanWebsite::route('/{record}/edit'),
        ];
    }
}
