<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    public function getTabs(): array
    {
        $query = SupportTicketResource::getEloquentQuery();

        return [
            'all' => Tab::make('Tümü')->badge((clone $query)->count()),
            'open' => Tab::make('Yanıt bekleyen')->badge((clone $query)->where('status', 'open')->count())->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'open')),
            'mine' => Tab::make('Bana atanan')->badge((clone $query)->where('assigned_to', auth()->id())->where('status', '!=', 'closed')->count())->modifyQueryUsing(fn (Builder $query): Builder => $query->where('assigned_to', auth()->id())->where('status', '!=', 'closed')),
            'unassigned' => Tab::make('Atanmayan')->badge((clone $query)->whereNull('assigned_to')->where('status', '!=', 'closed')->count())->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('assigned_to')->where('status', '!=', 'closed')),
            'overdue' => Tab::make('Geciken')->badge((clone $query)->where('status', '!=', 'closed')->where('due_at', '<', now())->count())->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', 'closed')->where('due_at', '<', now())),
            'closed' => Tab::make('Kapatılan')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'closed')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
