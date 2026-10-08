<?php

namespace App\Filament\Resources\SupportReplyTemplates;

use App\Filament\Resources\SupportReplyTemplates\Pages\ManageSupportReplyTemplates;
use App\Models\SupportReplyTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SupportReplyTemplateResource extends Resource
{
    protected static ?string $model = SupportReplyTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Destek';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Hazır yanıt';

    protected static ?string $pluralModelLabel = 'Hazır Yanıtlar';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('app_id')->label('Uygulama')->relationship('app', 'name')->searchable()->preload()->nullable()->exists('apps', 'id')->placeholder('Tüm uygulamalar')->helperText('Boş bırakırsanız bütün uygulamalarda kullanılabilir.'),
            TextInput::make('name')->label('Başlık')->required()->maxLength(120),
            Select::make('locale')->label('Dil')->options(['tr' => 'Türkçe', 'en' => 'İngilizce'])->required()->default('tr'),
            Textarea::make('body')->label('Yanıt metni')->required()->maxLength(5000)->rows(8)->columnSpanFull(),
            Toggle::make('is_active')->label('Kullanılabilir')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('app.name')->label('Uygulama')->placeholder('Tüm uygulamalar')->searchable(),
            TextColumn::make('name')->label('Başlık')->searchable(),
            TextColumn::make('locale')->label('Dil')->formatStateUsing(fn (string $state): string => $state === 'tr' ? 'Türkçe' : 'İngilizce'),
            IconColumn::make('is_active')->label('Kullanılabilir')->boolean(),
            TextColumn::make('updated_at')->label('Son güncelleme')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([SelectFilter::make('app')->label('Uygulama')->relationship('app', 'name')->preload(), SelectFilter::make('locale')->label('Dil')->options(['tr' => 'Türkçe', 'en' => 'İngilizce'])])
            ->recordActions([EditAction::make(), DeleteAction::make()])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportReplyTemplates::route('/')];
    }
}
