<?php

namespace App\Filament\Resources\StoreApps\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class StoreAppForm
{
    public static function configure(Schema $schema, ?int $appId = null): Schema
    {
        return $schema->components([
            Select::make('app_id')->default($appId)->hidden($appId !== null)->label('Uygulama')->relationship('app', 'name')->searchable()->required()->exists('apps', 'id')->live(),
            Select::make('platform')->label('Platform')->options(['ios' => 'iOS / App Store', 'android' => 'Android / Google Play'])->required()->in(['ios', 'android'])->live()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('app_id', $get('app_id'))),
            TextInput::make('identifier')->label('Bundle ID / Package Name')->required()->maxLength(255)
                ->regex('/^[A-Za-z][A-Za-z0-9_-]*(?:\.[A-Za-z][A-Za-z0-9_-]*)+$/')
                ->helperText('iOS: Bundle ID, Android: applicationId. Örnek: dev.sryya.shiftcal')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('platform', $get('platform'))),
            TextInput::make('apple_app_id')->label('Apple App ID')->helperText('App Store Connect sayısal uygulama kimliği; yayın öncesinde boş bırakılabilir.')
                ->visible(fn (Get $get): bool => $get('platform') === 'ios')->regex('/^[0-9]+$/')->maxLength(20)->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }
}
