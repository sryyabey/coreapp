<?php

namespace App\Filament\Resources\SubscriptionPlans\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SubscriptionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('app.name')->label('Uygulama')->searchable(), TextColumn::make('name')->label('Plan')->searchable(), TextColumn::make('period')->label('Dönem')->formatStateUsing(fn (string $state): string => $state === 'monthly' ? 'Aylık' : 'Yıllık'), TextColumn::make('catalog_price')->label('Referans fiyat'), TextColumn::make('currency')->label('Para birimi'), IconColumn::make('is_active')->label('Satışa açık')->boolean()])
            ->filters([SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name'), SelectFilter::make('period')->label('Dönem')->options(['monthly' => 'Aylık', 'yearly' => 'Yıllık']), TernaryFilter::make('is_active')->label('Satış durumu')])->recordActions([EditAction::make()]);
    }
}
