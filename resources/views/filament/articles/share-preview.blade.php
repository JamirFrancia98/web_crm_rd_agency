@php
    $datos = (array) ($getLivewire()->data ?? []);
    $titulo = trim((string) ($datos['og_title'] ?? '')) ?: (trim((string) ($datos['seo_title'] ?? '')) ?: (string) ($datos['titulo'] ?? ''));
    $descripcion = trim((string) ($datos['og_description'] ?? '')) ?: (trim((string) ($datos['meta_description'] ?? '')) ?: (string) ($datos['descripcion'] ?? ''));
    $imagen = trim((string) ($datos['imagen_src'] ?? ''));
    $dominio = 'revolutiondigital.agency';
    $urlImagen = $imagen !== '' ? (str_starts_with($imagen, 'http') ? $imagen : 'https://'.$dominio.'/'.ltrim($imagen, '/')) : null;
@endphp

<div style="max-width: 520px; border: 1px solid rgba(128,128,128,.35); border-radius: 12px; overflow: hidden; font-family: system-ui, sans-serif;">
    <div style="aspect-ratio: 1200 / 630; background: rgba(128,128,128,.15); display: flex; align-items: center; justify-content: center; overflow: hidden;">
        @if ($urlImagen)
            <img src="{{ $urlImagen }}" alt="" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
        @else
            <span style="font-size: 13px; opacity: .7; padding: 0 16px; text-align: center;">Sin imagen de portada: al compartir no habrá miniatura.</span>
        @endif
    </div>
    <div style="padding: 12px 14px; background: rgba(128,128,128,.08);">
        <div style="font-size: 12px; opacity: .65; text-transform: uppercase; letter-spacing: .04em;">{{ $dominio }}</div>
        <div style="font-size: 15px; font-weight: 600; margin-top: 2px; line-height: 1.3;">{{ $titulo !== '' ? $titulo : 'Sin título' }}</div>
        <div style="font-size: 13px; opacity: .8; margin-top: 4px; line-height: 1.4;">{{ $descripcion !== '' ? \Illuminate\Support\Str::limit($descripcion, 200) : 'Sin descripción' }}</div>
    </div>
</div>

<p style="font-size: 12px; opacity: .7; margin-top: 8px;">
    Así se vería en LinkedIn, WhatsApp o Facebook (aproximado: cada red lo recorta a su manera).
    Miniatura = imagen de portada · Título = título para redes → título SEO → título · Descripción = descripción para redes → meta descripción → descripción.
</p>
