<?php

namespace App\Filament\Resources\AppUsers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AppUserForm
{
    public static function configure(Schema $schema, ?int $appId = null): Schema
    {
        return $schema->components([
            Select::make('app_id')->default($appId)->hidden($appId !== null)->label('Uygulama')->relationship('app', 'name')->searchable()->required()->live()->disabledOn('edit')->dehydrated()->exists('apps', 'id'),
            Select::make('user_id')->label('Kullanıcı')->relationship('user', 'email')->searchable()->required()->disabledOn('edit')->dehydrated()->exists('users', 'id')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('app_id', $get('app_id'))),
            Toggle::make('is_active')->label('Üyelik aktif')->default(true),
            DateTimePicker::make('joined_at')->label('Katılım tarihi')->required()->default(now())->seconds(false),
            DateTimePicker::make('last_seen_at')->label('Son kullanım')->seconds(false)->disabled()->dehydrated(false),
        ]);
    }
}
