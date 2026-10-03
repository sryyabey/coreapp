<?php

namespace App\Filament\Resources\AppUsers\Pages;

use App\Filament\Resources\AppUsers\AppUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppUser extends CreateRecord
{
    protected static string $resource = AppUserResource::class;
}
