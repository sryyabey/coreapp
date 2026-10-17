<?php

namespace App\Filament\Resources\AppUsers\Tables;

use App\Models\AppUser;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('user.name')->label('Kullanıcı')->searchable()->sortable(),
            TextColumn::make('user.email')->label('E-posta')->searchable(),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
            TextColumn::make('joined_at')->label('Katılım')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('last_seen_at')->label('Son kullanım')->dateTime('d.m.Y H:i')->placeholder('Henüz yok')->sortable(),
        ])->filters([
            SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->searchable()->preload(),
            TernaryFilter::make('is_active')->label('Aktiflik')->trueLabel('Aktif')->falseLabel('Pasif'),
        ])->recordActions([
            EditAction::make(),
            self::resetPasswordAction(),
            Action::make('toggleMembership')->label(fn (AppUser $record): string => $record->is_active ? 'Pasife al' : 'Aktifleştir')
                ->requiresConfirmation()->visible(fn (AppUser $record): bool => auth()->user()->can('update', $record))
                ->authorize(fn (AppUser $record): bool => auth()->user()->can('update', $record))
                ->action(fn (AppUser $record): bool => $record->update(['is_active' => ! $record->is_active])),
        ])->defaultSort('joined_at', 'desc');
    }

    public static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Şifre yenile / üret')
            ->modalHeading('Kullanıcı şifresini yenile')
            ->modalDescription('Bu şifre ortak kullanıcı hesabının tüm uygulamalarında geçerlidir. Mevcut API oturumları kapatılır. Yeni şifreyi kaydetmeden önce kopyalayın; e-posta gönderilmez.')
            ->modalSubmitActionLabel('Şifreyi kaydet')
            ->visible(fn (AppUser $record): bool => auth()->user()->can('update', $record))
            ->authorize(fn (AppUser $record): bool => auth()->user()->can('update', $record))
            ->schema([
                TextInput::make('password')->label('Yeni şifre')->password()->revealable()
                    ->default(fn (): string => Str::password(20))->required()->minLength(12)->maxLength(128)
                    ->helperText('Otomatik oluşturulan şifreyi kullanabilir veya en az 12 karakterlik bir şifre yazabilirsiniz.'),
            ])
            ->action(function (AppUser $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $user = $record->user()->lockForUpdate()->firstOrFail();
                    $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
                    $user->tokens()->delete();
                });

                Notification::make()->title('Kullanıcı şifresi yenilendi')->success()->send();
            });
    }
}
