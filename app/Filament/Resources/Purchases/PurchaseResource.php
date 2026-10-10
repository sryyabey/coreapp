<?php

namespace App\Filament\Resources\Purchases;

use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Models\Purchase;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Abonelikler';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Satın alım';

    protected static ?string $pluralModelLabel = 'Satın Alımlar';

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
        return parent::getEloquentQuery()->with(['app', 'user', 'storeApp', 'storeProduct.subscriptionPlan']);
    }

    public static function statuses(): array
    {
        return ['active' => 'Aktif', 'grace' => 'Ödeme tolerans süresi', 'canceled' => 'Yenileme iptal edildi', 'expired' => 'Süresi doldu', 'billing_retry' => 'Ödeme tekrar deneniyor', 'pending' => 'Bekliyor', 'paused' => 'Duraklatıldı', 'on_hold' => 'Ödeme nedeniyle askıda', 'revoked' => 'İptal / geri ödeme', 'unknown' => 'Bilinmiyor'];
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'active' => 'success',
            'grace', 'canceled', 'billing_retry', 'pending', 'paused', 'on_hold' => 'warning',
            'revoked' => 'danger',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('id')->label('Kayıt no'),
            TextEntry::make('app.name')->label('Uygulama'),
            TextEntry::make('user.name')->label('Kullanıcı'),
            TextEntry::make('user.email')->label('E-posta'),
            TextEntry::make('storeApp.platform')->label('Platform')->formatStateUsing(fn (string $state): string => $state === 'ios' ? 'Apple' : 'Google Play'),
            TextEntry::make('storeApp.identifier')->label('Uygulama kimliği'),
            TextEntry::make('storeProduct.subscriptionPlan.name')->label('Plan'),
            TextEntry::make('storeProduct.product_id')->label('Ürün kimliği'),
            TextEntry::make('storeProduct.base_plan_id')->label('Temel plan kimliği')->placeholder('—'),
            TextEntry::make('environment')->label('Ortam')->badge(),
            TextEntry::make('status')->label('Mağaza durumu')->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)->badge()->color(fn (string $state): string => self::statusColor($state)),
            IconEntry::make('is_trial')->label('Ücretsiz deneme')->boolean(),
            IconEntry::make('auto_renews')->label('Otomatik yenileme')->boolean()->placeholder('Bilinmiyor'),
            TextEntry::make('expires_at')->label('Erişim bitişi')->dateTime('d.m.Y H:i')->placeholder('—'),
            TextEntry::make('verified_at')->label('Son doğrulama')->dateTime('d.m.Y H:i')->placeholder('—'),
            TextEntry::make('next_check_at')->label('Sonraki kontrol')->dateTime('d.m.Y H:i')->placeholder('—'),
            TextEntry::make('check_failures')->label('Başarısız kontrol sayısı'),
            TextEntry::make('last_check_error')->label('Son kontrol hatası')->placeholder('Hata yok'),
            TextEntry::make('created_at')->label('Kaydedilme')->dateTime('d.m.Y H:i'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Kayıt no')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('user.email')->label('Kullanıcı')->searchable()->description(fn (Purchase $record): ?string => $record->user?->name),
            TextColumn::make('storeApp.platform')->label('Mağaza')->badge()->formatStateUsing(fn (string $state): string => $state === 'ios' ? 'Apple' : 'Google Play'),
            TextColumn::make('storeProduct.product_id')->label('Ürün')->searchable()->description(fn (Purchase $record): ?string => $record->storeProduct?->subscriptionPlan?->name),
            TextColumn::make('environment')->label('Ortam')->badge()->color(fn (string $state): string => $state === 'production' ? 'success' : 'warning'),
            TextColumn::make('status')->label('Mağaza durumu')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)->color(fn (string $state): string => self::statusColor($state)),
            IconColumn::make('is_trial')->label('Deneme')->boolean(),
            IconColumn::make('auto_renews')->label('Yenileme')->boolean(),
            TextColumn::make('expires_at')->label('Erişim bitişi')->dateTime('d.m.Y H:i')->sortable()->color(fn (Purchase $record): string => $record->expires_at?->isPast() ? 'danger' : 'gray'),
            TextColumn::make('verified_at')->label('Son doğrulama')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('next_check_at')->label('Sonraki kontrol')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('check_failures')->label('Hatalı kontrol')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('last_check_error')->label('Son hata')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->searchable()->preload(),
            SelectFilter::make('platform')->label('Mağaza')->options(['ios' => 'Apple', 'android' => 'Google Play'])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $platform): Builder => $query->whereHas('storeApp', fn (Builder $query): Builder => $query->where('platform', $platform)))),
            SelectFilter::make('environment')->label('Ortam')->options(['production' => 'Canlı', 'sandbox' => 'Test']),
            SelectFilter::make('status')->label('Mağaza durumu')->options(self::statuses()),
            TernaryFilter::make('is_trial')->label('Ücretsiz deneme'),
            TernaryFilter::make('auto_renews')->label('Otomatik yenileme'),
            Filter::make('unexpired')->label('Erişim süresi devam edenler')->query(fn (Builder $query): Builder => $query->whereIn('status', ['active', 'grace', 'canceled'])->where('expires_at', '>', now())),
            Filter::make('check_errors')->label('Kontrol hatası olanlar')->query(fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('check_failures', '>', 0)->orWhereNotNull('last_check_error'))),
            Filter::make('expiry')->label('Erişim bitiş tarihi')->schema([DatePicker::make('from')->label('Başlangıç'), DatePicker::make('until')->label('Bitiş')])->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('expires_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('expires_at', '<=', $date))),
        ])->recordActions([ViewAction::make()->label('Detay')])->defaultSort('verified_at', 'desc')->paginationPageOptions([20, 50, 100])->poll('30s')->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return ['index' => ManagePurchases::route('/')];
    }
}
