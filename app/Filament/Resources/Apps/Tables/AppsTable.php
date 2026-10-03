<?php

namespace App\Filament\Resources\Apps\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AppsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('app_memberships_count')->label('Üye sayısı')->counts('appMemberships')->sortable(),
            TextColumn::make('store_apps_count')->label('Mağaza sayısı')->counts('storeApps'),
            TextColumn::make('slug')->label('Slug')->searchable()->copyable(),
            IconColumn::make('is_active')->label('Aktif')->boolean()->sortable(),
            TextColumn::make('support_email')->label('Destek e-postası')->placeholder('—')->searchable(),
            TextColumn::make('updated_at')->label('Güncelleme')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([
            TernaryFilter::make('is_active')->label('Aktiflik')->trueLabel('Aktif')->falseLabel('Pasif'),
        ])->recordActions([EditAction::make()])->defaultSort('name');
    }
}
