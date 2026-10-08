<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportReplyTemplate;
use App\Models\SupportTicket;
use App\Services\SupportAccess;
use App\Services\SupportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected string $view = 'filament.resources.support-tickets.view';

    #[Locked]
    public ?string $replyRequestId = null;

    #[Locked]
    public ?string $noteRequestId = null;

    #[Locked]
    public ?int $actionVersion = null;

    #[Locked]
    public int $messageLimit = 40;

    #[Locked]
    public int $activityLimit = 30;

    public function callMountedAction(array $arguments = []): mixed
    {
        try {
            return parent::callMountedAction($arguments);
        } catch (ValidationException $exception) {
            $schema = $this->getMountedActionSchema();
            $path = $schema?->getStatePath();
            $this->refreshTicket();
            if (! $path) {
                Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();

                return null;
            }
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                if (str_starts_with($field, $path.'.')) {
                    $errors[$field] = $messages;

                    continue;
                }
                $target = array_key_exists('body', $schema->getRawState()) ? 'body' : 'priority';
                $errors[$path.'.'.(array_key_exists($field, $schema->getRawState()) ? $field : $target)] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    public function refreshTicket(): void
    {
        Gate::authorize('view', $this->record);
        $this->record->refresh()->load(['app', 'user', 'assignee']);
    }

    public function moreMessages(): void
    {
        Gate::authorize('view', $this->record);
        $this->messageLimit += 40;
    }

    public function moreActivities(): void
    {
        Gate::authorize('view', $this->record);
        $this->activityLimit += 30;
    }

    public function conversation(): Collection
    {
        return $this->record->messages()->reorder('id', 'desc')->with('sender')->limit($this->messageLimit)->get()->reverse()->values();
    }

    public function history(): Collection
    {
        return $this->record->activities()->reorder('id', 'desc')->with('actor')->limit($this->activityLimit)->get();
    }

    public function relatedTickets(): Collection
    {
        return SupportTicketResource::getEloquentQuery()->where('user_id', $this->record->user_id)->where('id', '!=', $this->record->id)->latest('updated_at')->limit(5)->get();
    }

    private function templates(): Builder
    {
        return SupportReplyTemplate::where('is_active', true)->where('locale', $this->record->locale)->where(fn (Builder $q): Builder => $q->whereNull('app_id')->orWhere('app_id', $this->record->app_id));
    }

    public function templateOptions(): array
    {
        return $this->templates()->with('app')->orderBy('name')->get()->mapWithKeys(fn (SupportReplyTemplate $template): array => [$template->id => ($template->app?->name ?? 'Genel').' · '.$template->name])->all();
    }

    public function notificationSummary(): array
    {
        $membership = DB::table('app_users')->where('app_id', $this->record->app_id)->where('user_id', $this->record->user_id)->first();
        $lastId = $this->record->messages()->where('sender_type', 'staff')->max('id');
        $event = $lastId ? DB::table('support_push_outbox')->where('message_id', $lastId)->first() : null;
        $counts = $event ? DB::table('support_push_deliveries')->where('outbox_id', $event->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all() : [];

        return ['membership_active' => (bool) ($membership?->is_active ?? false), 'enabled' => (bool) ($membership?->support_replies ?? false),
            'status' => $event?->status, 'counts' => $counts];
    }

    private function saved(string $message): void
    {
        $this->refreshTicket();
        Notification::make()->title($message)->success()->send();
    }

    public function getTitle(): string
    {
        return 'Destek görüşmesi';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    private function ticketAction(string $name): Action
    {
        return collect($this->getTicketActions())->first(fn (Action $action): bool => $action->getName() === $name);
    }

    public function replyAction(): Action
    {
        return $this->ticketAction('reply');
    }

    public function takeAction(): Action
    {
        return $this->ticketAction('take');
    }

    public function detailsAction(): Action
    {
        return $this->ticketAction('details');
    }

    public function noteAction(): Action
    {
        return $this->ticketAction('note');
    }

    public function closeAction(): Action
    {
        return $this->ticketAction('close');
    }

    public function reopenAction(): Action
    {
        return $this->ticketAction('reopen');
    }

    protected function getTicketActions(): array
    {
        $allowed = fn (): bool => auth()->user()->can('update', $this->record);

        return [
            Action::make('reply')->label('Yanıt gönder')->icon('heroicon-o-paper-airplane')->visible($allowed)->authorize($allowed)
                ->disabled(fn (): bool => ! $this->record->app->is_active || ! $this->notificationSummary()['membership_active'])
                ->slideOver()->modalWidth('xl')->modalHeading('Kullanıcıya yanıt yaz')
                ->modalDescription('Yanıtınız aşağıdaki kullanıcının uygulama içi destek görüşmesine gönderilir.')
                ->modalContent(fn () => view('filament.resources.support-tickets.reply-context', ['ticket' => $this->record]))
                ->modalSubmitActionLabel('Yanıtı gönder')->modalCancelActionLabel('Vazgeç')
                ->mountUsing(function (Schema $schema): void {
                    $this->refreshTicket();
                    $this->actionVersion = $this->record->version;
                    $this->replyRequestId = (string) Str::uuid();
                    $schema->fill(['close_after_reply' => false]);
                })
                ->extraModalFooterActions([Action::make('refreshDraft')->label('Görüşmeyi yenile')->color('gray')->visible(fn (): bool => $this->actionVersion !== $this->record->version)->authorize($allowed)->action(function (): void {
                    $this->refreshTicket();
                    $this->actionVersion = $this->record->version;
                    Notification::make()->title('Görüşme yenilendi. Son kullanıcı mesajını kontrol edip yanıtınızı gönderin.')->warning()->send();
                })])
                ->schema([
                    Select::make('template_id')->label('Hazır yanıt kullan (isteğe bağlı)')->placeholder('Bir şablon seçin')->options(fn (): array => $this->templateOptions())->searchable()->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            Gate::authorize('view', $this->record);
                            if (! $state) {
                                return;
                            }
                            $template = $this->templates()->find($state);
                            if ($template) {
                                $set('body', $template->body);
                            }
                        }),
                    Textarea::make('body')->label('Yanıt')->required()->maxLength(5000)->rows(8)->placeholder('Kullanıcıya göndereceğiniz yanıtı yazın…'),
                    Toggle::make('close_after_reply')->label('Gönderdikten sonra talebi kapat')->helperText('Kapalıysa görüşme yanıtlandı durumunda kalır.')->default(false),
                ])
                ->action(function (array $data): void {
                    app(SupportService::class)->reply($this->record, auth()->user(), $data['body'], $this->replyRequestId ?? (string) Str::uuid(), $data['close_after_reply'] ?? false, $this->actionVersion);
                    $this->saved('Yanıt doğru uygulamanın destek kutusuna kaydedildi; bildirim kuyruğa alındı.');
                }),
            Action::make('take')->label('Üzerime al')->visible(fn (): bool => $allowed() && $this->record->assigned_to === null)->authorize($allowed)
                ->action(function (): void {
                    app(SupportService::class)->take($this->record, auth()->user());
                    $this->saved('Talep size atandı.');
                }),
            Action::make('details')->label('Atama ve öncelik')->visible($allowed)->authorize($allowed)
                ->mountUsing(function (Schema $schema): void {
                    $this->refreshTicket();
                    $this->actionVersion = $this->record->version;
                    $schema->fill($this->record->only(['assigned_to', 'priority', 'category', 'due_at']));
                })
                ->schema([
                    Select::make('assigned_to')->label('Görevli')->options(fn (): array => app(SupportAccess::class)->agents($this->record->app_id))->searchable()->nullable()->placeholder('Atanmadı'),
                    Select::make('priority')->label('Öncelik')->options(SupportTicket::priorities())->required(),
                    Select::make('category')->label('Kategori')->options(SupportTicket::categories())->required(),
                    DateTimePicker::make('due_at')->label('Yanıt hedefi')->seconds(false)->nullable()->helperText('Hedef tarihi geçen açık talepler Geciken kuyruğunda görünür.'),
                ])
                ->action(function (array $data): void {
                    app(SupportService::class)->updateDetails($this->record, auth()->user(), $data, $this->actionVersion);
                    $this->saved('Talep bilgileri güncellendi.');
                }),
            Action::make('note')->label('İç not ekle')->color('gray')->visible($allowed)->authorize($allowed)
                ->modalDescription('Yalnızca destek ekibi görür. Kullanıcıya mesaj veya telefon bildirimi gönderilmez.')
                ->mountUsing(function (Schema $schema): void {
                    $this->noteRequestId = (string) Str::uuid();
                    $schema->fill();
                })
                ->schema([Textarea::make('body')->label('İç not')->required()->maxLength(5000)->rows(6)])
                ->action(function (array $data): void {
                    app(SupportService::class)->note($this->record, auth()->user(), $data['body'], $this->noteRequestId ?? (string) Str::uuid());
                    $this->saved('İç not eklendi.');
                }),
            Action::make('close')->label('Talebi kapat')->color('gray')->requiresConfirmation()->visible(fn (): bool => $allowed() && $this->record->status !== 'closed')->authorize($allowed)
                ->mountUsing(function (): void {
                    $this->refreshTicket();
                    $this->actionVersion = $this->record->version;
                })
                ->action(function (): void {
                    app(SupportService::class)->close($this->record, auth()->user(), $this->actionVersion);
                    $this->saved('Talep kapatıldı.');
                }),
            Action::make('reopen')->label('Yeniden aç')->requiresConfirmation()->visible(fn (): bool => $allowed() && $this->record->status === 'closed')->authorize($allowed)
                ->mountUsing(function (): void {
                    $this->refreshTicket();
                    $this->actionVersion = $this->record->version;
                })
                ->action(function (): void {
                    app(SupportService::class)->reopen($this->record, auth()->user(), $this->actionVersion);
                    $this->saved('Talep yeniden açıldı.');
                }),
        ];
    }
}
