<?php

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('app_id')->label('Uygulama')->relationship('app', 'name')->searchable()->required()->live()->disabledOn('edit')->dehydrated()->exists('apps', 'id'),
            TextInput::make('name')->label('Plan adı')->required()->maxLength(255),
            TextInput::make('slug')->label('Plan anahtarı')->required()->maxLength(255)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('app_id', $get('app_id'))),
            Select::make('period')->label('Yenileme dönemi')->options(['monthly' => 'Aylık', 'yearly' => 'Yıllık'])->required()->in(['monthly', 'yearly'])->disabledOn('edit')->dehydrated(),
            TextInput::make('catalog_price')->label('Referans fiyat')->numeric()->minValue(0)->maxValue(99999999.99)->rule('decimal:0,2')->helperText('Mobilde gösterilen gerçek fiyat mağazadan alınır.'),
            TextInput::make('currency')->label('Para birimi')->default('USD')->required()->regex('/^[A-Z]{3}$/')->maxLength(3),
            TagsInput::make('features')->label('Ücretli özellikler')->default(['premium'])->nestedRecursiveRules(['string', 'max:100', 'regex:/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/'])->helperText('Örnek: premium, cloud_backup, advanced_reports. Boş plan özellik hakkı vermez.'),
            Toggle::make('is_active')->label('Satışa açık')->default(true),
        ]);
    }
}
