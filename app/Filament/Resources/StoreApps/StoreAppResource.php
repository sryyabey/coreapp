<?php

namespace App\Filament\Resources\StoreApps;

use App\Filament\Resources\StoreApps\Pages\CreateStoreApp;
use App\Filament\Resources\StoreApps\Pages\EditStoreApp;
use App\Filament\Resources\StoreApps\Pages\ListStoreApps;
use App\Filament\Resources\StoreApps\Schemas\StoreAppForm;
use App\Filament\Resources\StoreApps\Tables\StoreAppsTable;
use App\Models\StoreApp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StoreAppResource extends Resource
{
    protected static ?string $model = StoreApp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Uygulamalar';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'identifier';

    protected static ?string $modelLabel = 'Mağaza uygulaması';

    protected static ?string $pluralModelLabel = 'Mağaza Uygulamaları';

    public static function form(Schema $schema): Schema
    {
        return StoreAppForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreAppsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreApps::route('/'),
            'create' => CreateStoreApp::route('/create'),
            'edit' => EditStoreApp::route('/{record}/edit'),
        ];
    }
}
