<?php

namespace App\Filament\Resources\StoreNotifications;

use App\Filament\Resources\StoreNotifications\Pages\ManageStoreNotifications;
use App\Models\StoreNotification;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StoreNotificationResource extends Resource
{
    protected static ?string $model = StoreNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|UnitEnum|null $navigationGroup = 'Abonelikler';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Mağaza bildirimi';

    protected static ?string $pluralModelLabel = 'Mağaza Bildirimleri';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('storeApp.app');
    }

    public static function statuses(): array
    {
        return ['pending' => 'İşlem bekliyor', 'waiting' => 'Satın alım eşleşmesi bekliyor', 'processed' => 'İşlendi', 'ignored' => 'İşlem gerektirmiyor', 'failed' => 'Hatalı', 'exhausted' => 'Deneme sınırına ulaştı'];
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'processed' => 'success',
            'pending', 'waiting' => 'warning',
            'failed', 'exhausted' => 'danger',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('storeApp.app.name')->label('Uygulama'),
            TextEntry::make('storeApp.platform')->label('Platform'),
            TextEntry::make('event_id')->label('Olay kimliği')->copyable(),
            TextEntry::make('type')->label('Bildirim türü'),
            TextEntry::make('environment')->label('Ortam')->badge(),
            TextEntry::make('status')->label('İşleme durumu')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)->color(fn (string $state): string => self::statusColor($state)),
            TextEntry::make('attempts')->label('İşleme denemesi'),
            TextEntry::make('last_error')->label('Son hata')->placeholder('Hata yok'),
            TextEntry::make('created_at')->label('Alınma')->dateTime('d.m.Y H:i'),
            TextEntry::make('processed_at')->label('İşlenme')->dateTime('d.m.Y H:i')->placeholder('Henüz işlenmedi'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('storeApp.app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('storeApp.platform')->label('Mağaza')->badge()->formatStateUsing(fn (string $state): string => $state === 'ios' ? 'Apple' : 'Google Play'),
            TextColumn::make('type')->label('Bildirim türü')->searchable(),
            TextColumn::make('environment')->label('Ortam')->badge()->color(fn (string $state): string => $state === 'production' ? 'success' : 'warning'),
            TextColumn::make('status')->label('İşleme durumu')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)->color(fn (string $state): string => self::statusColor($state)),
            TextColumn::make('attempts')->label('Deneme')->sortable(),
            TextColumn::make('last_error')->label('Son hata')->placeholder('—')->wrap(),
            TextColumn::make('created_at')->label('Alınma')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('processed_at')->label('İşlenme')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),
            TextColumn::make('event_id')->label('Olay kimliği')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('app')->label('Uygulama')->relationship('storeApp.app', 'name')->searchable()->preload(),
            SelectFilter::make('storeApp')->label('Mağaza uygulaması')->relationship('storeApp', 'identifier')->searchable()->preload(),
            SelectFilter::make('environment')->label('Ortam')->options(['production' => 'Canlı', 'sandbox' => 'Test']),
            SelectFilter::make('status')->label('İşleme durumu')->options(self::statuses()),
            Filter::make('attention')->label('İlgilenilmesi gerekenler')->query(fn (Builder $query): Builder => $query->whereIn('status', ['waiting', 'failed', 'exhausted'])),
        ])->recordActions([ViewAction::make()->label('Detay')])->defaultSort('created_at', 'desc')->paginationPageOptions([20, 50, 100])->poll('30s')->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return ['index' => ManageStoreNotifications::route('/')];
    }
}
