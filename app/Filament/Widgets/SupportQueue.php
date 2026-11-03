<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class SupportQueue extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('manage')) === true && SupportTicketResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table->heading('Öncelikli destek kuyruğu')
            ->description('Yanıt bekleyen talepler; gecikenler ve acil olanlar önce gösterilir.')
            ->query(fn (): Builder => static::canView()
                ? SupportTicketResource::getEloquentQuery()->where('status', 'open')
                : SupportTicket::query()->whereRaw('1 = 0'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN due_at < ? THEN 0 ELSE 1 END', [now()])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
                ->orderBy('created_at'))
            ->columns([
                TextColumn::make('app.name')->label('Uygulama')->badge(),
                TextColumn::make('subject')->label('Konu')->wrap()->limit(70)
                    ->description(fn (SupportTicket $record): string => $record->customer_name),
                TextColumn::make('priority')->label('Öncelik')->badge()
                    ->formatStateUsing(fn (string $state): string => SupportTicket::priorities()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger', 'high' => 'warning', default => 'gray',
                    }),
                TextColumn::make('assignee.name')->label('Görevli')->placeholder('Atanmadı'),
                TextColumn::make('due_at')->label('Hedef tarih')->dateTime('d.m H:i')->placeholder('Belirlenmedi')
                    ->color(fn (SupportTicket $record): string => $record->due_at?->isPast() ? 'danger' : 'gray'),
                TextColumn::make('created_at')->label('Açılış')->since(),
            ])
            ->filters([SelectFilter::make('app_id')->label('Uygulama')->options(fn (): array => static::canView() ? SupportTicketResource::appOptions() : [])])
            ->recordUrl(fn (SupportTicket $record): ?string => Gate::allows('view', $record) ? SupportTicketResource::getUrl('view', ['record' => $record]) : null)
            ->headerActions([Action::make('all')->label('Tüm talepler')->url(SupportTicketResource::getUrl())])
            ->emptyStateHeading('Yanıt bekleyen talep yok')
            ->emptyStateDescription('Yeni bir destek talebi geldiğinde burada görünecek.')
            ->paginationPageOptions([5, 10, 25])->defaultPaginationPageOption(5)->poll('60s');
    }
}
