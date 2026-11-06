<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Support\BlogCatalog;

/**
 * Reglas de bloqueo de un artículo. Replican las de la web (rd-astro, src/content.config.js): si aquí pasan,
 * `astro build` no debe rechazar el archivo exportado. Si la web cambia sus reglas, se cambian aquí también.
 */
final class ArticleGate
{
    /**
     * Motivos que impiden aprobar o publicar (la web no compilaría el artículo).
     *
     * @param  bool  $paraAprobar  al aprobar, la fecha de aprobación aún no existe: la pone el flujo.
     * @return list<string>
     */
    public static function problems(Article $a, bool $paraAprobar = false): array
    {
        $p = [];
        $len = fn (?string $v): int => mb_strlen(trim((string) $v));

        if ($len($a->titulo) < 10 || $len($a->titulo) > 90) {
            $p[] = 'El título debe tener entre 10 y 90 caracteres (tiene '.$len($a->titulo).').';
        }
        if ($len($a->descripcion) < 50 || $len($a->descripcion) > 200) {
            $p[] = 'La descripción debe tener entre 50 y 200 caracteres (tiene '.$len($a->descripcion).').';
        }
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $a->slug)) {
            $p[] = 'El slug solo admite minúsculas, números y guiones, sin fecha.';
        }
        if (! array_key_exists((string) $a->idioma, BlogCatalog::IDIOMAS)) {
            $p[] = 'Idioma no válido.';
        }
        if (! array_key_exists((string) $a->categoria, BlogCatalog::CATEGORIAS)) {
            $p[] = 'Falta una categoría válida.';
        }

        // Regla legal: con riesgo, la revisión legal debe estar aprobada; pendiente bloquea siempre.
        if ($a->riesgo_legal !== 'ninguno' && $a->revision_legal !== 'aprobada') {
            $p[] = "El riesgo legal «{$a->riesgo_legal}» exige revisión legal aprobada (hoy: {$a->revision_legal}).";
        }
        if ($a->revision_legal === 'pendiente' && $a->riesgo_legal === 'ninguno') {
            $p[] = 'La revisión legal está pendiente.';
        }

        if ($a->cifras_propias && self::sinDato($a->evidencia)) {
            $p[] = 'Tiene cifras propias y falta la evidencia.';
        }
        if ($a->menciona_clientes) {
            $p[] = 'Menciona clientes: no hay forma de acreditar el permiso escrito; consulta con Jamir.';
        }

        $validas = $a->sources->filter(fn ($s) => preg_match('#^https?://#', (string) $s->url) && $s->fecha_consulta !== null);
        if ($validas->count() < 2) {
            $p[] = 'Se necesitan al menos 2 fuentes con enlace (http/https) y fecha de consulta (hay '.$validas->count().').';
        }
        if (self::sinDato($a->aporte_propio)) {
            $p[] = 'Falta el aporte propio de Revolution Digital.';
        }
        if (! $paraAprobar && $a->fecha_aprobacion === null) {
            $p[] = 'Falta la fecha de aprobación.';
        }
        if ($a->imagen_src && (self::sinDato($a->imagen_alt) || self::sinDato($a->imagen_licencia))) {
            $p[] = 'Una imagen necesita texto alternativo y licencia.';
        }
        if (self::sinDato($a->body_md)) {
            $p[] = 'El artículo no tiene texto.';
        }

        return $p;
    }

    /**
     * Avisos que no impiden aprobar, pero sí conviene resolver antes de publicar.
     *
     * @return list<string>
     */
    public static function warnings(Article $a): array
    {
        $w = [];
        if (self::sinDato($a->keyword_oficial)) {
            $w[] = 'La palabra clave oficial está pendiente (la fija Web y SEO; las propuestas no tienen volumen verificado).';
        }
        if (self::sinDato($a->servicio_relacionado)) {
            $w[] = 'Sin servicio relacionado: el botón final no enlazará a ninguna página de servicio.';
        }
        if (! $a->imagen_src) {
            $w[] = 'Sin imagen de portada: se usará el arte por defecto de la web.';
        }
        $n = mb_strlen(trim((string) $a->seo_title));
        if ($n === 0 || $n > 65) {
            $w[] = 'El título SEO debería tener entre 1 y 65 caracteres (tiene '.$n.').';
        }
        $m = mb_strlen(trim((string) $a->meta_description));
        if ($m < 120 || $m > 160) {
            $w[] = 'La meta descripción ideal tiene entre 120 y 160 caracteres (tiene '.$m.').';
        }
        if (! self::sinDato($a->nota_revisor)) {
            $w[] = 'Hay una nota interna para el revisor: léela antes de aprobar.';
        }

        return $w;
    }

    private static function sinDato(?string $v): bool
    {
        $v = trim((string) $v);

        return $v === '' || $v === 'PENDIENTE';
    }
}
