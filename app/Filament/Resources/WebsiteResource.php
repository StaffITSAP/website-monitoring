<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebsiteResource\Pages;
use App\Filament\Resources\WebsiteResource\RelationManagers;
use App\Models\Website;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Monitoring';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_website')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('url')
                    ->required()
                    ->url()
                    ->maxLength(255),
                Forms\Components\Toggle::make('aktif')
                    ->required()
                    ->default(true),
                Forms\Components\TextInput::make('interval_pemeriksaan')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(60)
                    ->suffix('menit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_website')
                    ->searchable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable(),
                Tables\Columns\IconColumn::make('aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('interval_pemeriksaan')
                    ->numeric()
                    ->suffix(' menit')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('periksa_sekarang')
                    ->label('Periksa Sekarang')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Website $record) {
                        try {
                            app(\App\Services\MonitorWebsiteService::class)
                                ->periksaWebsiteTunggal($record);

                            Notification::make()
                                ->title('Pemeriksaan Berhasil')
                                ->body('Website ' . $record->nama_website . ' berhasil diperiksa')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Pemeriksaan Gagal')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('lihat_log')
                    ->label('Lihat Log')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn($record) => \App\Filament\Resources\LogPemeriksaanWebsiteResource::getUrl('index', [
                        'tableFilters' => [
                            'website_id' => [
                                'value' => $record->id
                            ]
                        ]
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LogPemeriksaanWebsiteRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsites::route('/'),
            'create' => Pages\CreateWebsite::route('/create'),
            'edit' => Pages\EditWebsite::route('/{record}/edit'),
        ];
    }
}
