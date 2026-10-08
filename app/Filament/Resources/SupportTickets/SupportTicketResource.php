<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Destek';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $modelLabel = 'Destek talebi';

    protected static ?string $pluralModelLabel = 'Destek Talepleri';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('user.name')->label('Kullanıcı')->searchable(),
            TextColumn::make('user.email')->label('E-posta')->searchable(),
            TextColumn::make('subject')->label('Konu')->searchable()->limit(60),
            TextColumn::make('status')->label('Durum')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'open' => 'warning', 'answered' => 'success', default => 'gray'
                }),
            TextColumn::make('updated_at')->label('Son hareket')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->searchable()->preload(),
            SelectFilter::make('status')->label('Durum')->options(self::statuses()),
        ])->recordActions([ViewAction::make()->label('Görüşmeyi aç')])->defaultSort('updated_at', 'desc');
    }

    public static function statuses(): array
    {
        return ['open' => 'Yanıt bekliyor', 'answered' => 'Yanıtlandı', 'closed' => 'Kapatıldı'];
    }

    public static function getPages(): array
    {
        return ['index' => ListSupportTickets::route('/'), 'view' => ViewSupportTicket::route('/{record}')];
    }
}
