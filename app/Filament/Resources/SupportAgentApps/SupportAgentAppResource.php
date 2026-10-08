<?php

namespace App\Filament\Resources\SupportAgentApps;

use App\Filament\Resources\SupportAgentApps\Pages\ManageSupportAgentApps;
use App\Models\SupportAgentApp;
use App\Models\User;
use App\Services\SupportAccess;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class SupportAgentAppResource extends Resource
{
    protected static ?string $model = SupportAgentApp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Destek';

    protected static ?string $modelLabel = 'Destek erişimi';

    protected static ?string $pluralModelLabel = 'Görevli Erişimleri';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('app_id')->label('Uygulama')->relationship('app', 'name')->searchable()->preload()->required()->exists('apps', 'id')->live(),
            Select::make('user_id')->label('Destek görevlisi')->options(fn (): array => User::whereHas('roles')->orderBy('name')->get()
                ->filter(fn (User $user): bool => app(SupportAccess::class)->panelUser($user) && $user->can('ViewAny:SupportTicket') && $user->can('View:SupportTicket'))
                ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' · '.$user->email])->all())
                ->searchable()->required()->exists('users', 'id')->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('app_id', $get('app_id')))
                ->helperText('Önce görevliye panel rolü ve destek görüntüleme izinlerini verin. Tüm uygulamalar izni olan kişiler bu listeden bağımsız erişebilir.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('app.name')->label('Uygulama')->searchable()->sortable(),
            TextColumn::make('user.name')->label('Görevli')->searchable(),
            TextColumn::make('user.email')->label('E-posta')->searchable(),
            TextColumn::make('created_at')->label('Yetki tarihi')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->preload()->searchable()])
            ->recordActions([DeleteAction::make()->label('Erişimi kaldır')->modalDescription('Bu uygulamanın talepleri artık görevliye görünmez. Tüm uygulamalar izni varsa bu izin ayrıca kaldırılmalıdır.')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportAgentApps::route('/')];
    }
}
