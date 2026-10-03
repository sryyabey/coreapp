<?php

namespace App\Filament\Resources\AppUsers\Tables;

use App\Models\AppUser;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AppUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('user.name')->label('Kullanıcı')->searchable()->sortable(),
            TextColumn::make('user.email')->label('E-posta')->searchable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
            TextColumn::make('joined_at')->label('Katılım')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('last_seen_at')->label('Son kullanım')->dateTime('d.m.Y H:i')->placeholder('Henüz yok')->sortable(),
        ])->filters([
            SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->searchable()->preload(),
            TernaryFilter::make('is_active')->label('Aktiflik')->trueLabel('Aktif')->falseLabel('Pasif'),
        ])->recordActions([
            EditAction::make(),
            Action::make('toggleMembership')->label(fn (AppUser $record): string => $record->is_active ? 'Pasife al' : 'Aktifleştir')
                ->requiresConfirmation()->visible(fn (AppUser $record): bool => auth()->user()->can('update', $record))
                ->authorize(fn (AppUser $record): bool => auth()->user()->can('update', $record))
                ->action(fn (AppUser $record): bool => $record->update(['is_active' => ! $record->is_active])),
        ])->defaultSort('joined_at', 'desc');
    }
}
