<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\ArticleStatusLog;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected static ?string $title = 'Nuevo artículo';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Clave interna única: el tema (si lo hay) o una aleatoria, más el idioma.
        $base = filled($data['id_tema'] ?? null) ? $data['id_tema'] : 'N'.Str::upper(Str::random(5));
        $data['post_ref'] = $base.'-'.$data['idioma'];
        $data['fecha_borrador'] = now()->toDateString();
        $data['word_count'] = str_word_count(strip_tags((string) ($data['body_md'] ?? '')));

        return $data;
    }

    protected function afterCreate(): void
    {
        ArticleStatusLog::create([
            'article_id' => $this->record->id,
            'estado_anterior' => null,
            'estado_nuevo' => $this->record->estado,
            'usuario' => auth()->user()->name.' <'.auth()->user()->email.'>',
            'nota' => 'Artículo creado.',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return ArticleResource::getUrl('edit', ['record' => $this->record]);
    }
}
