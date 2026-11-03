<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Apps\AppResource;
use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\StoreApps\StoreAppResource;
use App\Filament\Resources\StoreNotifications\StoreNotificationResource;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\Purchase;
use App\Models\StoreNotification;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class QuickActions extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.quick-actions';

    public static function canView(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('manage')) === true;
    }

    public function getColumnSpan(): int|string|array
    {
        return SupportActivityChart::canView() ? 1 : 'full';
    }

    protected function getViewData(): array
    {
        $links = $attention = [];
        if (! static::canView()) {
            return compact('links', 'attention');
        }
        foreach ([
            [SupportTicketResource::class, 'Destek talepleri', 'Görüşmeleri yanıtla', 'heroicon-o-chat-bubble-left-right'],
            [AppResource::class, 'Uygulama kataloğu', 'Uygulamalar ve entegrasyonlar', 'heroicon-o-squares-2x2'],
            [AppUserResource::class, 'Uygulama üyelikleri', 'Kullanıcı erişimlerini yönet', 'heroicon-o-users'],
            [PurchaseResource::class, 'Satın alımlar', 'Abonelikleri ve erişimleri incele', 'heroicon-o-credit-card'],
            [StoreAppResource::class, 'Mağaza uygulamaları', 'Apple ve Google Play ayarları', 'heroicon-o-device-phone-mobile'],
            [StoreNotificationResource::class, 'Mağaza bildirimleri', 'Ödeme olaylarını takip et', 'heroicon-o-bell-alert'],
        ] as [$resource, $label, $description, $icon]) {
            if ($resource::canViewAny()) {
                $links[] = ['label' => $label, 'description' => $description, 'icon' => $icon, 'url' => $resource::getUrl()];
            }
        }
        if (SupportTicketResource::canViewAny()) {
            $query = SupportTicketResource::getEloquentQuery();
            foreach ([
                ['Hedef tarihi geçen talepler', (clone $query)->where('status', '!=', 'closed')->where('due_at', '<', now())->count(), ['overdue' => ['isActive' => true]]],
                ['Acil ve yanıt bekleyen talepler', (clone $query)->where('status', 'open')->where('priority', 'urgent')->count(), ['status' => ['value' => 'open'], 'priority' => ['value' => 'urgent']]],
            ] as [$label, $count, $filters]) {
                if ($count > 0) {
                    $attention[] = ['label' => $label, 'count' => $count, 'url' => SupportTicketResource::getUrl('index', ['filters' => $filters])];
                }
            }
        }
        if (PurchaseResource::canViewAny()) {
            $count = Purchase::query()->where(fn (Builder $query): Builder => $query->where('check_failures', '>', 0)->orWhereNotNull('last_check_error'))->count();
            if ($count > 0) {
                $attention[] = ['label' => 'Doğrulama hatası olan satın alımlar', 'count' => $count, 'url' => PurchaseResource::getUrl('index', ['filters' => ['check_errors' => ['isActive' => true]]])];
            }
        }
        if (StoreNotificationResource::canViewAny()) {
            $count = StoreNotification::query()->whereIn('status', ['waiting', 'failed', 'exhausted'])->count();
            if ($count > 0) {
                $attention[] = ['label' => 'İnceleme bekleyen mağaza bildirimleri', 'count' => $count, 'url' => StoreNotificationResource::getUrl('index', ['filters' => ['attention' => ['isActive' => true]]])];
            }
        }

        return compact('links', 'attention');
    }
}
