<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\SupportActivityChart;
use App\Filament\Widgets\SupportQueue;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Genel Bakış';

    public function getTitle(): string|Htmlable
    {
        return 'Genel Bakış';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Uygulamalar, abonelikler ve destek operasyonları tek ekranda.';
    }

    public function getWidgets(): array
    {
        return [OverviewStats::class, QuickActions::class, SupportActivityChart::class, SupportQueue::class];
    }
}
