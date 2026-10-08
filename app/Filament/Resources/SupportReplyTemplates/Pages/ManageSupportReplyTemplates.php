<?php

namespace App\Filament\Resources\SupportReplyTemplates\Pages;

use App\Filament\Resources\SupportReplyTemplates\SupportReplyTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportReplyTemplates extends ManageRecords
{
    protected static string $resource = SupportReplyTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
