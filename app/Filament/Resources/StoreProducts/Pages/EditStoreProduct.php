<?php

namespace App\Filament\Resources\StoreProducts\Pages;

use App\Filament\Resources\StoreProducts\StoreProductResource;
use Filament\Resources\Pages\EditRecord;

class EditStoreProduct extends EditRecord
{
    protected static string $resource = StoreProductResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
