<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Schemas\ArticleForm;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use App\Models\Article;
use App\Support\BlogCatalog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'artículo';

    protected static ?string $pluralModelLabel = 'artículos';

    protected static ?string $navigationLabel = 'Artículos';

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?string $recordTitleAttribute = 'titulo';

    /** El revisor legal lee y marca la revisión legal; no crea artículos. */
    public static function canCreate(): bool
    {
        return auth()->user()?->role !== \App\Enums\UserRole::Legal;
    }

    /** Los artículos no se borran: se descartan y queda el historial. */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Número de artículos esperando revisión, visible en el menú. */
    public static function getNavigationBadge(): ?string
    {
        $n = Article::where('estado', BlogCatalog::EN_REVISION)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Artículos en revisión';
    }

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
