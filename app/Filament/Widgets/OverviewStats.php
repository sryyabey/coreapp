<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Apps\AppResource;
use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Purchase;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('manage')) === true
            && (AppResource::canViewAny() || AppUserResource::canViewAny() || PurchaseResource::canViewAny() || SupportTicketResource::canViewAny());
    }

    protected function getColumns(): int|array|null
    {
        $count = count($this->getCachedStats());

        return ['default' => 1, '@lg' => min(2, max(1, $count)), '@xl' => max(1, $count)];
    }

    protected function getStats(): array
    {
        if (! static::canView()) {
            return [];
        }
        $stats = [];
        if (AppResource::canViewAny()) {
            $counts = App::query()->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')->first();
            $stats[] = Stat::make('Aktif uygulamalar', (int) $counts->active)
                ->description($counts->total.' uygulama kayıtlı')->icon(Heroicon::OutlinedSquares2x2)
                ->color('primary')->url(AppResource::getUrl());
        }
        if (AppUserResource::canViewAny()) {
            $counts = AppUser::query()->where('is_active', true)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN last_seen_at >= ? THEN 1 ELSE 0 END) as recent', [now()->subDays(7)])->first();
            $stats[] = Stat::make('Aktif üyelikler', (int) $counts->total)
                ->description('Son 7 günde '.((int) $counts->recent).' üyelik kullanıldı')->icon(Heroicon::OutlinedUsers)
                ->color('info')->url(AppUserResource::getUrl());
        }
        if (PurchaseResource::canViewAny()) {
            $count = Purchase::query()->where('environment', 'production')
                ->whereHas('storeProduct', fn (Builder $query): Builder => $query->where('environment', 'production'))
                ->whereIn('status', ['active', 'grace', 'canceled'])->where('expires_at', '>', now())->count();
            $stats[] = Stat::make('Canlı abonelikler', $count)
                ->description('Erişim süresi devam eden satın alımlar')->icon(Heroicon::OutlinedCreditCard)
                ->color('success')->url(PurchaseResource::getUrl('index', ['filters' => ['environment' => ['value' => 'production'], 'unexpired' => ['isActive' => true]]]));
        }
        if (SupportTicketResource::canViewAny()) {
            $count = SupportTicketResource::getEloquentQuery()->where('status', 'open')->count();
            $unassigned = SupportTicketResource::getEloquentQuery()->where('status', 'open')->whereNull('assigned_to')->count();
            $stats[] = Stat::make('Yanıt bekleyen talepler', $count)
                ->description($unassigned.' talebe henüz görevli atanmadı')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color($count > 0 ? 'warning' : 'success')->url(SupportTicketResource::getUrl('index', ['filters' => ['status' => ['value' => 'open']]]));
        }

        return $stats;
    }
}
