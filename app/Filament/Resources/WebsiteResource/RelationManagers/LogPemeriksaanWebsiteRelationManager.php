<?php

namespace App\Filament\Resources\WebsiteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LogPemeriksaanWebsiteRelationManager extends RelationManager
{
    protected static string $relationship = 'logPemeriksaan';

    protected static ?string $title = 'Log Pemeriksaan Website';

    protected static ?string $modelLabel = 'Log Pemeriksaan';
    protected static ?string $pluralModelLabel = 'Log Pemeriksaan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode_status')
                    ->numeric()
                    ->label('Kode Status HTTP'),
                Forms\Components\TextInput::make('waktu_respons')
                    ->numeric()
                    ->suffix('detik')
                    ->label('Waktu Respons'),
                Forms\Components\Toggle::make('berhasil')
                    ->required()
                    ->label('Berhasil Diakses'),
                Forms\Components\Textarea::make('pesan_error')
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->label('Pesan Error'),
                Forms\Components\TextInput::make('screenshot_path')
                    ->maxLength(255)
                    ->label('Path Screenshot'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('created_at')
            ->columns([
                Tables\Columns\TextColumn::make('kode_status')
                    ->numeric()
                    ->label('Kode Status')
                    ->color(fn ($record) => $record->berhasil ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('waktu_respons')
                    ->numeric()
                    ->suffix(' detik')
                    ->label('Waktu Respons')
                    ->sortable(),
                Tables\Columns\IconColumn::make('berhasil')
                    ->boolean()
                    ->label('Berhasil'),
                Tables\Columns\TextColumn::make('pesan_error')
                    ->limit(50)
                    ->label('Pesan Error')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Waktu Pemeriksaan')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('berhasil')
                    ->label('Hanya yang berhasil')
                    ->query(fn (Builder $query) => $query->where('berhasil', true)),
                Tables\Filters\Filter::make('gagal')
                    ->label('Hanya yang gagal')
                    ->query(fn (Builder $query) => $query->where('berhasil', false)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('lihat_screenshot')
                    ->label('Lihat Screenshot')
                    ->icon('heroicon-o-photo')
                    ->url(fn ($record) => $record->screenshot_path ? storage_path('app/public/' . $record->screenshot_path) : null)
                    ->openUrlInNewTab()
                    ->hidden(fn ($record) => empty($record->screenshot_path)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}