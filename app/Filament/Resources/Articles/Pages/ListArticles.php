<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Support\BlogCatalog as C;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected static ?string $title = 'Artículos del blog';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo artículo'),
        ];
    }

    /** Pestañas por estado con su contador: quien revisa ve de un vistazo qué le toca. */
    public function getTabs(): array
    {
        $tab = fn (string $label, ?string $estado) => Tab::make($label)
            // El parámetro debe llamarse $query: Filament lo inyecta por nombre.
            ->modifyQueryUsing(fn (Builder $query) => $estado ? $query->where('estado', $estado) : $query)
            ->badge(Article::query()->when($estado, fn ($q) => $q->where('estado', $estado))->count());

        return [
            'revision' => $tab('Por revisar', C::EN_REVISION),
            'borrador' => $tab('Borradores', C::BORRADOR),
            'aprobado' => $tab('Aprobados', C::APROBADO),
            'publicado' => $tab('Publicados', C::PUBLICADO),
            'descartado' => $tab('Descartados', C::DESCARTADO),
            'todos' => $tab('Todos', null),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return auth()->user()?->hasRole(UserRole::Revisor, UserRole::Admin) ? 'revision' : 'borrador';
    }
}
