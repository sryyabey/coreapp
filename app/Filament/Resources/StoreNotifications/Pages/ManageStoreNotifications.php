<?php

namespace App\Filament\Resources\StoreNotifications\Pages;

use App\Filament\Resources\StoreNotifications\StoreNotificationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStoreNotifications extends ManageRecords
{
    protected static string $resource = StoreNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
