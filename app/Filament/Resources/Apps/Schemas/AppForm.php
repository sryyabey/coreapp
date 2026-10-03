<?php

namespace App\Filament\Resources\Apps\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AppForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Uygulama adı')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)->helperText('Örnek: shiftcal. Entegrasyonlarda kullanılacak kalıcı uygulama anahtarı.'),
            Textarea::make('description')->label('Açıklama')->maxLength(5000)->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif')->default(true),
            TextInput::make('support_email')->label('Destek e-postası')->email()->maxLength(255),
            TextInput::make('website_url')->label('Web sitesi')->url()->maxLength(2048),
            TextInput::make('privacy_policy_url')->label('Gizlilik politikası')->url()->maxLength(2048),
            TextInput::make('terms_url')->label('Kullanım koşulları')->url()->maxLength(2048),
        ]);
    }
}
