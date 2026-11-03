<?php

namespace App\Filament\Resources\Apps\Pages;

use App\Filament\Resources\Apps\AppResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class EditApp extends EditRecord
{
    protected static string $resource = AppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rotateSupportKey')->label('Destek API anahtarı oluştur / yenile')
                ->visible(fn (): bool => Gate::allows('update', $this->record))
                ->requiresConfirmation()
                ->modalDescription('Önceki anahtar geçersiz olur. Yeni anahtar yalnızca bir kez gösterilir; dış uygulamanın sunucusunda saklayın.')
                ->action(function (): void {
                    Gate::authorize('update', $this->record);
                    $key = Str::random(64);
                    $this->record->forceFill(['support_api_key_hash' => hash('sha256', $key)])->save();
                    Notification::make()->title('Destek API anahtarı')->body($key)->persistent()->success()->send();
                }),
            Action::make('revokeSupportKey')->label('Destek API erişimini kapat')->color('danger')
                ->visible(fn (): bool => Gate::allows('update', $this->record) && $this->record->support_api_key_hash !== null)
                ->requiresConfirmation()
                ->action(function (): void {
                    Gate::authorize('update', $this->record);
                    $this->record->forceFill(['support_api_key_hash' => null])->save();
                    Notification::make()->title('Dış destek API erişimi kapatıldı')->success()->send();
                }),
        ];
    }
}
