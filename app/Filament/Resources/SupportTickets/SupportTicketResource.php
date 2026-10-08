<?php

namespace App\Filament\Resources\SupportTickets;

use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\App;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportAccess;
use App\Services\SupportService;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Destek';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $modelLabel = 'Destek talebi';

    protected static ?string $pluralModelLabel = 'Destek Talepleri';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return app(SupportAccess::class)->scope(parent::getEloquentQuery(), auth()->user())->with(['app', 'user', 'assignee'])->withCount('messages');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Talep no')->copyable()->limit(8)->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('app.name')->label('Uygulama')->badge()->searchable()->sortable()->description(fn (SupportTicket $record): string => $record->app->slug),
            TextColumn::make('user.name')->label('Kullanıcı')->searchable()->description(fn (SupportTicket $record): string => $record->user->email),
            TextColumn::make('user.email')->label('E-posta')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('subject')->label('Konu')->searchable()->limit(60)->wrap(),
            TextColumn::make('status')->label('Durum')->badge()->formatStateUsing(fn (string $state): string => self::statuses()[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'open' => 'warning','answered' => 'success',default => 'gray'
                }),
            TextColumn::make('priority')->label('Öncelik')->badge()->formatStateUsing(fn (string $state): string => SupportTicket::priorities()[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'urgent' => 'danger','high' => 'warning',default => 'gray'
                }),
            TextColumn::make('assignee.name')->label('Görevli')->placeholder('Atanmadı')->searchable(),
            TextColumn::make('category')->label('Kategori')->formatStateUsing(fn (string $state): string => SupportTicket::categories()[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('due_at')->label('Hedef tarih')->dateTime('d.m.Y H:i')->placeholder('Belirlenmedi')->sortable()
                ->color(fn (SupportTicket $record): string => $record->status !== 'closed' && $record->due_at?->isPast() ? 'danger' : 'gray'),
            TextColumn::make('last_customer_message_at')->label('Son kullanıcı mesajı')->since()->sortable(),
            TextColumn::make('messages_count')->label('Mesaj')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Son hareket')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('app_id')->label('Uygulama')->options(fn (): array => self::appOptions())->searchable(),
            SelectFilter::make('status')->label('Durum')->options(self::statuses()),
            SelectFilter::make('priority')->label('Öncelik')->options(SupportTicket::priorities()),
            SelectFilter::make('category')->label('Kategori')->options(SupportTicket::categories()),
            SelectFilter::make('assigned_to')->label('Görevli')->options(fn (): array => User::whereIn('id', app(SupportAccess::class)->scope(SupportTicket::query(), auth()->user())->whereNotNull('assigned_to')->select('assigned_to'))->orderBy('name')->pluck('name', 'id')->all())->searchable(),
            Filter::make('unassigned')->label('Atanmayanlar')->query(fn (Builder $query): Builder => $query->whereNull('assigned_to')),
            Filter::make('overdue')->label('Hedef tarihi geçenler')->query(fn (Builder $query): Builder => $query->where('status', '!=', 'closed')->where('due_at', '<', now())),
            Filter::make('created')->label('Oluşturulma tarihi')->schema([DatePicker::make('from')->label('Başlangıç'), DatePicker::make('until')->label('Bitiş')])
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
        ])->recordActions([ViewAction::make()->label('Görüşmeyi aç')])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('take')->label('Seçilenleri üzerime al')->requiresConfirmation()->authorizeIndividualRecords('update')
                    ->action(fn (Collection $records) => self::bulk($records, 'take'))->deselectRecordsAfterCompletion(),
                BulkAction::make('priority')->label('Öncelik değiştir')->schema([Select::make('priority')->label('Öncelik')->options(SupportTicket::priorities())->required()])->authorizeIndividualRecords('update')
                    ->action(fn (Collection $records, array $data) => self::bulk($records, 'priority', $data))->deselectRecordsAfterCompletion(),
                BulkAction::make('close')->label('Seçilenleri kapat')->requiresConfirmation()->authorizeIndividualRecords('update')
                    ->action(fn (Collection $records) => self::bulk($records, 'close'))->deselectRecordsAfterCompletion(),
            ])->visible(fn (): bool => auth()->user()->can('Update:SupportTicket'))])->groups([Group::make('app.name')->label('Uygulama')->collapsible()])
            ->defaultSort('updated_at', 'desc')->paginationPageOptions([20, 50, 100])->poll('30s')->persistFiltersInSession()->persistSearchInSession();
    }

    public static function appOptions(): array
    {
        $query = App::query()->orderBy('name');
        if (! app(SupportAccess::class)->allApps(auth()->user())) {
            $query->whereIn('id', DB::table('support_agent_apps')->select('app_id')->where('user_id', auth()->id()));
        }

        return $query->pluck('name', 'id')->all();
    }

    public static function bulk(Collection $records, string $operation, array $data = []): void
    {
        try {
            DB::transaction(function () use ($records, $operation, $data): void {
                foreach ($records->sortBy('id') as $record) {
                    Gate::authorize('update', $record);
                    $service = app(SupportService::class);
                    match ($operation) {
                        'take' => $service->take($record, auth()->user()),
                        'close' => $service->close($record, auth()->user(), $record->version),
                        'priority' => $service->updateDetails($record, auth()->user(), ['priority' => $data['priority'], 'category' => $record->category, 'assigned_to' => $record->assigned_to, 'due_at' => $record->due_at], $record->version),
                    };
                }
            });
        } catch (ValidationException $exception) {
            Notification::make()->title('Toplu işlem uygulanmadı')->body(collect($exception->errors())->flatten()->first())->danger()->send();

            throw new Halt;
        }
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
