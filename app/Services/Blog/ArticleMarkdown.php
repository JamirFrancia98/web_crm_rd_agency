<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Support\BlogCatalog;
use DomainException;

/**
 * Genera el archivo .md que lee la web (rd-astro, src/content/blog/): mismo frontmatter, mismo orden que
 * blog/_PLANTILLA.md. Solo se exportan artículos aprobados o publicados. La nota interna del revisor NO se exporta.
 * Subir el archivo a la web sigue siendo un paso manual aprobado por Jamir.
 */
final class ArticleMarkdown
{
    /** @return array{filename: string, content: string} */
    public static function export(Article $a): array
    {
        if (! in_array($a->estado, BlogCatalog::COMPILABLES, true)) {
            throw new DomainException('Solo se exportan artículos aprobados o publicados (este está '.BlogCatalog::etiquetaEstado($a->estado).').');
        }
        $a->loadMissing('sources');

        $fecha = $a->fecha_aprobacion ?? $a->fecha_borrador ?? now();
        $filename = $fecha->format('Y-m-d').'-'.$a->slug.'.'.$a->idioma.'.md';

        $fm = [];
        $fm[] = 'titulo: '.self::s($a->titulo);
        $fm[] = 'descripcion: '.self::s($a->descripcion);
        $fm[] = 'slug: '.self::s($a->slug);
        $fm[] = 'idioma: '.$a->idioma;
        $fm[] = 'grupo_traduccion: '.self::s($a->grupo_traduccion);
        $fm[] = 'id_tema: '.self::s($a->id_tema);
        $fm[] = '';
        $fm[] = 'estado: '.self::s($a->estado);
        $fm[] = 'generado_con_ia: '.self::b($a->generado_con_ia);
        $fm[] = 'autor: '.self::s($a->autor);
        $fm[] = 'revisor: '.self::s($a->revisor);
        $fm[] = 'fecha_borrador: '.self::d($a->fecha_borrador);
        $fm[] = 'fecha_revision: '.self::d($a->fecha_revision);
        $fm[] = 'fecha_aprobacion: '.self::d($a->fecha_aprobacion);
        $fm[] = 'fecha_publicacion: '.self::d($a->fecha_publicacion);
        $fm[] = 'motivo_descarte: '.self::s($a->motivo_descarte);
        $fm[] = '';
        $fm[] = 'categoria: '.$a->categoria;
        $fm[] = 'tipo: '.$a->tipo;
        $fm[] = 'palabra_clave: '.self::s($a->keyword_oficial ?: 'PENDIENTE');
        $fm[] = 'intencion_busqueda: '.self::s($a->intencion_busqueda);
        $fm[] = 'servicio_relacionado: '.self::s($a->servicio_relacionado);
        $fm[] = '';
        $fm[] = 'riesgo_legal: '.$a->riesgo_legal;
        $fm[] = 'revision_legal: '.$a->revision_legal;
        $fm[] = 'cifras_propias: '.self::b($a->cifras_propias);
        $fm[] = 'evidencia: '.self::s($a->evidencia);
        $fm[] = 'menciona_clientes: '.self::b($a->menciona_clientes);
        $fm[] = 'aporte_propio: '.self::s($a->aporte_propio);
        $fm[] = '';

        if ($a->sources->isEmpty()) {
            $fm[] = 'fuentes: []';
        } else {
            $fm[] = 'fuentes:';
            foreach ($a->sources as $s) {
                $fm[] = '  - titulo: '.self::s($s->titulo);
                $fm[] = '    editorial: '.self::s($s->editorial);
                $fm[] = '    url: '.self::s($s->url);
                $fm[] = '    fecha_consulta: '.self::d($s->fecha_consulta);
            }
        }
        $fm[] = '';
        $fm[] = 'imagen:';
        $fm[] = '  src: '.self::s($a->imagen_src);
        $fm[] = '  alt: '.self::s($a->imagen_alt);
        $fm[] = '  licencia: '.self::s($a->imagen_licencia);

        $content = "---\n".implode("\n", $fm)."\n---\n\n".rtrim((string) $a->body_md)."\n";

        return ['filename' => $filename, 'content' => $content];
    }

    /** Cadena entre comillas dobles: JSON es YAML válido y escapa comillas, saltos de línea y caracteres de control. */
    private static function s(?string $v): string
    {
        return json_encode((string) $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function b(bool $v): string
    {
        return $v ? 'true' : 'false';
    }

    private static function d($v): string
    {
        return $v ? $v->format('Y-m-d') : '""';
    }
}
