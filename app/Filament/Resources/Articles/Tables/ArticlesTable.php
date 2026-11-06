<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Support\BlogCatalog;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Artículo')->searchable()->limit(70)->wrap()
                    ->description(fn ($record) => $record->post_ref.' · /'.$record->slug.'/'),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => BlogCatalog::etiquetaEstado($state))
                    ->color(fn (string $state) => match ($state) {
                        BlogCatalog::BORRADOR => 'gray',
                        BlogCatalog::EN_REVISION => 'warning',
                        BlogCatalog::APROBADO => 'success',
                        BlogCatalog::PUBLICADO => 'info',
                        default => 'danger',
                    })->sortable(),
                TextColumn::make('idioma')->label('Idioma')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),
                TextColumn::make('categoria')->label('Categoría')
                    ->formatStateUsing(fn (string $state) => BlogCatalog::CATEGORIAS[$state] ?? $state),
                TextColumn::make('riesgo_legal')->label('Riesgo legal')->badge()
                    ->formatStateUsing(fn (string $state) => BlogCatalog::RIESGOS[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'ninguno' ? 'gray' : 'danger')
                    ->toggleable(),
                TextColumn::make('fecha_aprobacion')->label('Aprobado')->date('d/m/Y')->placeholder('—')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Modificado')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('idioma')->label('Idioma')->options(BlogCatalog::IDIOMAS),
                SelectFilter::make('categoria')->label('Categoría')->options(BlogCatalog::CATEGORIAS),
                SelectFilter::make('riesgo_legal')->label('Riesgo legal')->options(BlogCatalog::RIESGOS),
            ])
            ->recordUrl(fn ($record) => \App\Filament\Resources\Articles\ArticleResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make()->label('Abrir'),
            ])
            ->emptyStateHeading('Todavía no hay artículos aquí');
    }
}
