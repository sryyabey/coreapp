<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class SupportActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Son 7 gün · Destek hareketi';

    protected ?string $description = 'Açılan talepler ve kapalı taleplerin son kapanış tarihleri.';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = '60s';

    protected ?array $options = ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]], 'plugins' => ['legend' => ['position' => 'bottom']]];

    public static function canView(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('manage')) === true && SupportTicketResource::canViewAny();
    }

    protected function getData(): array
    {
        if (! static::canView()) {
            return ['labels' => [], 'datasets' => []];
        }
        $start = today()->subDays(6);
        $end = today()->addDay();
        $query = SupportTicketResource::getEloquentQuery()->select([])->withoutEagerLoads();
        $opened = (clone $query)->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $closed = (clone $query)->where('status', 'closed')->where('closed_at', '>=', $start)->where('closed_at', '<', $end)
            ->selectRaw('DATE(closed_at) as day, COUNT(*) as total')->groupByRaw('DATE(closed_at)')->pluck('total', 'day');
        $labels = $created = $completed = [];
        for ($day = $start->copy(); $day->lt($end); $day->addDay()) {
            $labels[] = $day->format('d.m');
            $created[] = (int) ($opened[$day->toDateString()] ?? 0);
            $completed[] = (int) ($closed[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'datasets' => [
            ['label' => 'Açılan talepler', 'data' => $created, 'borderColor' => '#d97706', 'backgroundColor' => '#d97706', 'tension' => 0.3],
            ['label' => 'Kapanan talepler', 'data' => $completed, 'borderColor' => '#059669', 'backgroundColor' => '#059669', 'tension' => 0.3],
        ]];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
