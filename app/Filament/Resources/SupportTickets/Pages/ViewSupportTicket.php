<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Services\SupportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected string $view = 'filament.resources.support-tickets.view';

    public ?string $replyRequestId = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')->label('Yanıt gönder')
                ->visible(fn (): bool => auth()->user()->can('update', $this->record))
                ->authorize(fn (): bool => auth()->user()->can('update', $this->record))
                ->modalHeading('Uygulamadaki destek kutusuna yanıt gönder')
                ->modalDescription(fn (): string => $this->record->app->name.' · '.$this->record->user->email)
                ->mountUsing(function (Schema $schema): void {
                    $this->replyRequestId = (string) Str::uuid();
                    $schema->fill();
                })
                ->schema([Textarea::make('body')->label('Yanıt')->required()->maxLength(5000)->rows(7)])
                ->action(function (array $data): void {
                    app(SupportService::class)->reply($this->record, auth()->user(), $data['body'], $this->replyRequestId ?? (string) Str::uuid());
                    $this->record->refresh();
                    Notification::make()->title('Yanıt ilgili uygulamanın destek kutusuna gönderildi.')->success()->send();
                }),
            Action::make('close')->label('Talebi kapat')->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status !== 'closed' && auth()->user()->can('update', $this->record))
                ->authorize(fn (): bool => auth()->user()->can('update', $this->record))
                ->action(function (): void {
                    app(SupportService::class)->close($this->record, auth()->user());
                    $this->record->refresh();
                }),
        ];
    }
}
