<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Biblioteca de documentos de contenido: solo se leen. Se editan en su archivo y se vuelven a importar. */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    protected static ?string $navigationLabel = 'Documentos';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('categoria')->label('Categoría')->badge()->formatStateUsing(fn (string $state) => Document::CATEGORIAS[$state] ?? $state),
                TextEntry::make('ruta')->label('Archivo de origen')->copyable(),
                TextEntry::make('sincronizado_en')->label('Última copia')->dateTime('d/m/Y H:i'),
            ]),
            Section::make()->schema([
                TextEntry::make('contenido_md')->hiddenLabel()->markdown()->prose()->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Documento')->searchable()->wrap()->description(fn (Document $d) => $d->ruta),
                TextColumn::make('categoria')->label('Categoría')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => Document::CATEGORIAS[$state] ?? $state)->sortable(),
                TextColumn::make('sincronizado_en')->label('Copiado')->since()->sortable(),
            ])
            ->defaultSort('categoria')
            ->filters([SelectFilter::make('categoria')->label('Categoría')->options(Document::CATEGORIAS)])
            ->recordActions([ViewAction::make()->label('Leer')])
            ->recordUrl(fn (Document $d) => static::getUrl('view', ['record' => $d]))
            ->emptyStateHeading('Todavía no hay documentos')
            ->emptyStateDescription('Se cargan con: php artisan crm:importar-documentos <carpeta de negocio>');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'view' => ViewDocument::route('/{record}'),
        ];
    }
}
