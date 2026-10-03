<?php

namespace App\Filament\Resources\StoreProducts\Schemas;

use App\Models\StoreApp;
use App\Models\SubscriptionPlan;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class StoreProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('environment')->label('Ortam')->options(['production' => 'Production — Canlı', 'sandbox' => 'Sandbox — Test'])->default('production')->required()->in(['production', 'sandbox'])->disabledOn('edit')->dehydrated()->live(),
            Select::make('subscription_plan_id')->label('Plan')->relationship('subscriptionPlan', 'name')->getOptionLabelFromRecordUsing(fn (SubscriptionPlan $record): string => $record->app->name.' / '.$record->name.' / '.$record->period)->searchable()->required()->exists('subscription_plans', 'id')->live()->afterStateUpdated(function (Set $set): void {
                $set('store_app_id', null);
                $set('base_plan_id', '');
            }),
            Select::make('store_app_id')->label('Mağaza')->relationship('storeApp', 'identifier', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('app_id', SubscriptionPlan::find($get('subscription_plan_id'))?->app_id))
                ->getOptionLabelFromRecordUsing(fn (StoreApp $record): string => $record->platform.' / '.$record->identifier)->searchable()->required()->live()->exists('store_apps', 'id')
                ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    if (StoreApp::find($value)?->app_id !== SubscriptionPlan::find($get('subscription_plan_id'))?->app_id) {
                        $fail('Plan ve mağaza aynı uygulamaya ait olmalı.');
                    }
                }]),
            TextInput::make('product_id')->label('Mağaza Product ID')->required()->maxLength(255)->regex('/^[A-Za-z0-9][A-Za-z0-9._-]*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('store_app_id', $get('store_app_id'))->where('environment', $get('environment'))->where('base_plan_id', StoreApp::find($get('store_app_id'))?->platform === 'android' ? $get('base_plan_id') : '')),
            TextInput::make('base_plan_id')->label('Google Play Base Plan ID')->default('')->maxLength(255)->regex('/^[a-z0-9][a-z0-9-]*$/')->required(fn (Get $get): bool => StoreApp::find($get('store_app_id'))?->platform === 'android')->visible(fn (Get $get): bool => StoreApp::find($get('store_app_id'))?->platform === 'android'),
            Toggle::make('is_active')->label('Satışa açık')->default(true),
        ]);
    }
}
