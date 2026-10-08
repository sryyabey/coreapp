<?php

namespace App\Filament\Resources\SupportAgentApps\Pages;

use App\Filament\Resources\SupportAgentApps\SupportAgentAppResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportAgentApps extends ManageRecords
{
    protected static string $resource = SupportAgentAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
