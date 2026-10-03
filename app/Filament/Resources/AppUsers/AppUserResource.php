<?php

namespace App\Filament\Resources\AppUsers;

use App\Filament\Resources\AppUsers\Pages\CreateAppUser;
use App\Filament\Resources\AppUsers\Pages\EditAppUser;
use App\Filament\Resources\AppUsers\Pages\ListAppUsers;
use App\Filament\Resources\AppUsers\Schemas\AppUserForm;
use App\Filament\Resources\AppUsers\Tables\AppUsersTable;
use App\Models\AppUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AppUserResource extends Resource
{
    protected static ?string $model = AppUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Uygulamalar';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Uygulama üyeliği';

    protected static ?string $pluralModelLabel = 'Uygulama Üyelikleri';

    public static function form(Schema $schema): Schema
    {
        return AppUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppUsersTable::configure($table);
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
            'index' => ListAppUsers::route('/'),
            'create' => CreateAppUser::route('/create'),
            'edit' => EditAppUser::route('/{record}/edit'),
        ];
    }
}
