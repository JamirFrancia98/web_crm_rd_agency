@php
    $datos = (array) ($getLivewire()->data ?? []);
    $md = (string) ($datos['body_md'] ?? '');
    $html = \Illuminate\Support\Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
@endphp

<div style="max-width: 760px;">
    @php($banner = \App\Support\Portada::url($datos['imagen_archivo'] ?? null, trim((string) ($datos['imagen_src'] ?? '')) ?: null))
    @if ($banner)
        <img src="{{ $banner }}" alt="{{ $datos['imagen_alt'] ?? '' }}" style="width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 12px; margin-bottom: 16px;" loading="lazy">
    @endif
    <h1 style="font-size: 26px; font-weight: 700; line-height: 1.25; margin-bottom: 6px;">{{ $datos['titulo'] ?? '' }}</h1>
    <p style="opacity: .75; margin-bottom: 18px;">{{ $datos['descripcion'] ?? '' }}</p>
    @if (trim($md) === '')
        <p style="opacity: .6;">Todavía no hay texto.</p>
    @else
        <div class="fi-prose">{!! $html !!}</div>
    @endif
    <p style="font-size: 12px; opacity: .6; margin-top: 18px;">Vista aproximada: la web aplica su propio diseño, pero el contenido y los títulos son los mismos.</p>
</div>
