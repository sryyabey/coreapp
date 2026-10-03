<?php

namespace App\Filament\Resources\StoreApps\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StoreAppsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('platform')->label('Platform')->badge()->formatStateUsing(fn (string $state): string => $state === 'ios' ? 'iOS' : 'Android'),
            TextColumn::make('identifier')->label('Bundle ID / Package Name')->searchable()->copyable(),
            TextColumn::make('apple_app_id')->label('Apple App ID')->placeholder('—')->copyable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->filters([
            SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->searchable()->preload(),
            SelectFilter::make('platform')->label('Platform')->options(['ios' => 'iOS', 'android' => 'Android']),
            TernaryFilter::make('is_active')->label('Aktiflik'),
        ])->recordActions([EditAction::make()])->defaultSort('created_at', 'desc');
    }
}
