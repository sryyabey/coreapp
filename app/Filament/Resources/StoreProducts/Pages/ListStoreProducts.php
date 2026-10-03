<?php

namespace App\Filament\Resources\StoreProducts\Pages;

use App\Filament\Resources\StoreProducts\StoreProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoreProducts extends ListRecords
{
    protected static string $resource = StoreProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
