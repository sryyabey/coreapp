<?php

namespace App\Filament\Resources\Apps\RelationManagers;

use App\Filament\Resources\StoreApps\Schemas\StoreAppForm;
use App\Filament\Resources\StoreApps\StoreAppResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StoreAppsRelationManager extends RelationManager
{
    protected static string $relationship = 'storeApps';

    protected static ?string $relatedResource = StoreAppResource::class;

    protected static ?string $title = 'Mağaza Kayıtları';

    public function form(Schema $schema): Schema
    {
        return StoreAppForm::configure($schema, $this->getOwnerRecord()->id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
