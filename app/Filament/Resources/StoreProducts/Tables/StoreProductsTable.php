<?php

namespace App\Filament\Resources\StoreProducts\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StoreProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('subscriptionPlan.app.name')->label('Uygulama')->searchable(), TextColumn::make('subscriptionPlan.name')->label('Plan'), TextColumn::make('subscriptionPlan.period')->label('Dönem'), TextColumn::make('storeApp.platform')->label('Platform'), TextColumn::make('environment')->label('Ortam')->badge()->color(fn (string $state): string => $state === 'production' ? 'success' : 'warning'), TextColumn::make('product_id')->label('Product ID')->searchable()->copyable(), TextColumn::make('base_plan_id')->label('Base Plan ID')->placeholder('—'), IconColumn::make('is_active')->label('Satışa açık')->boolean()])
            ->filters([SelectFilter::make('subscriptionPlan')->label('Plan')->relationship('subscriptionPlan', 'name'), SelectFilter::make('environment')->label('Ortam')->options(['production' => 'Production', 'sandbox' => 'Sandbox']), TernaryFilter::make('is_active')->label('Satış durumu')])->recordActions([EditAction::make()]);
    }
}
