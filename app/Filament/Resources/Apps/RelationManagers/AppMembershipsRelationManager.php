<?php

namespace App\Filament\Resources\Apps\RelationManagers;

use App\Filament\Resources\AppUsers\AppUserResource;
use App\Filament\Resources\AppUsers\Schemas\AppUserForm;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AppMembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'appMemberships';

    protected static ?string $relatedResource = AppUserResource::class;

    protected static ?string $title = 'Üyelikler';

    public function form(Schema $schema): Schema
    {
        return AppUserForm::configure($schema, $this->getOwnerRecord()->id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
