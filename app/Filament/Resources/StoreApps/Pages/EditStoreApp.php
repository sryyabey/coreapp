<?php

namespace App\Filament\Resources\StoreApps\Pages;

use App\Filament\Resources\StoreApps\StoreAppResource;
use Filament\Resources\Pages\EditRecord;

class EditStoreApp extends EditRecord
{
    protected static string $resource = StoreAppResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
