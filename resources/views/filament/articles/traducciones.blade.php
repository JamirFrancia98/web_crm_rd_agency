@php
    $a = $getRecord();
    $otros = collect();
    if ($a) {
        $otros = \App\Models\Article::query()
            ->where('id', '!=', $a->id)
            ->where(function ($q) use ($a) {
                $q->when(filled($a->grupo_traduccion), fn ($qq) => $qq->orWhere('grupo_traduccion', $a->grupo_traduccion));
                $q->when(filled($a->id_tema) && $a->id_tema !== 'T00', fn ($qq) => $qq->orWhere('id_tema', $a->id_tema));
            })
            ->orderBy('idioma')->get();
    }
@endphp

<div style="font-size: 14px;">
    <strong>Versiones en otros idiomas:</strong>
    @if ($otros->isEmpty())
        <span style="opacity: .7;">ninguna todavía. Este artículo solo existe en {{ \App\Support\BlogCatalog::IDIOMAS[$a?->idioma] ?? $a?->idioma }}.</span>
    @else
        @foreach ($otros as $o)
            <a href="{{ \App\Filament\Resources\Articles\ArticleResource::getUrl('edit', ['record' => $o]) }}" style="margin-left: 10px; text-decoration: underline;">
                {{ strtoupper($o->idioma) }} · {{ $o->titulo }}
            </a>
        @endforeach
    @endif
</div>
