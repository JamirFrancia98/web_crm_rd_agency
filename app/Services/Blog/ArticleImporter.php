<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Models\ArticleKeyword;
use App\Models\ArticleSource;
use App\Models\ArticleStatusLog;
use Illuminate\Support\Facades\DB;

/**
 * Importa artículos desde blog/exportacion/blog_posts.json (paquete preparado por el área de contenido).
 * Es una copia derivada: si no coincide con el frontmatter de los borradores, manda el frontmatter.
 * Si el artículo ya existe en el CRM NO se toca (los cambios hechos aquí no se pisan).
 */
final class ArticleImporter
{
    /** @return 'creado'|'omitido' */
    public static function importar(array $p): string
    {
        $c = $p['contenido'];
        $e = $p['editorial'];
        $seo = $p['seo'] ?? [];
        $img = $p['imagen'] ?? [];
        $ref = $p['id_tema'].'-'.$c['idioma'];

        if (Article::where('post_ref', $ref)->orWhere(fn ($q) => $q->where('idioma', $c['idioma'])->where('slug', $c['slug']))->exists()) {
            return 'omitido';
        }

        $nul = fn ($v) => ($v === null || trim((string) $v) === '' || str_starts_with((string) $v, 'PENDIENTE')) ? null : $v;
        $fecha = fn ($v) => $nul($v);

        DB::transaction(function () use ($p, $c, $e, $seo, $img, $ref, $nul, $fecha) {
            $a = new Article([
                'post_ref' => $ref,
                'id_tema' => $p['id_tema'],
                'grupo_traduccion' => $nul($c['grupo_traduccion'] ?? null),
                'idioma' => $c['idioma'],
                'slug' => $c['slug'],
                'titulo' => $c['titulo'],
                'descripcion' => $c['descripcion'],
                'seo_title' => $nul($seo['title_tag'] ?? null),
                'meta_description' => $nul($seo['meta_description'] ?? null),
                'categoria' => $c['categoria'],
                'tipo' => $c['tipo'],
                'intencion_busqueda' => $nul($c['intencion_busqueda'] ?? null),
                'servicio_relacionado' => $nul($c['servicio_relacionado'] ?? null),
                'keyword_oficial' => $nul($seo['palabra_clave_oficial'] ?? null),
                'keyword_estado' => 'propuesta',
                'canonical_path' => $nul($seo['ruta_propuesta'] ?? null),
                'canonical_url' => $nul($seo['url_canonica_propuesta'] ?? null),
                'robots' => 'noindex,nofollow',
                'imagen_src' => $nul($img['src'] ?? null),
                'imagen_alt' => $nul($img['alt'] ?? null),
                'imagen_licencia' => $nul($img['licencia'] ?? null),
                'autor' => $e['autor'] ?: 'Revolution Digital',
                'revisor' => $nul($e['revisor'] ?? null),
                'generado_con_ia' => (bool) ($e['generado_con_ia'] ?? true),
                'riesgo_legal' => $e['riesgo_legal'],
                'revision_legal' => $e['revision_legal'],
                'cifras_propias' => (bool) ($e['cifras_propias'] ?? false),
                'evidencia' => $nul($e['evidencia'] ?? null),
                'menciona_clientes' => (bool) ($e['menciona_clientes'] ?? false),
                'aporte_propio' => $nul($e['aporte_propio'] ?? null),
                'nota_revisor' => $nul($e['nota_para_el_revisor'] ?? null),
                'fecha_borrador' => $fecha($e['fecha_borrador'] ?? null),
                'fecha_revision' => $fecha($e['fecha_revision'] ?? null),
                'fecha_aprobacion' => $fecha($e['fecha_aprobacion'] ?? null),
                'fecha_publicacion' => $fecha($e['fecha_publicacion'] ?? null),
                'word_count' => (int) ($c['palabras'] ?? 0),
                'archivo_origen' => $nul($p['archivo_origen'] ?? null),
                'body_md' => $c['cuerpo_markdown'],
            ]);
            $a->forceFill(['estado' => $p['estado']])->save();

            foreach (array_values($p['fuentes'] ?? []) as $i => $s) {
                ArticleSource::create([
                    'article_id' => $a->id, 'orden' => $i + 1,
                    'titulo' => $s['titulo'], 'editorial' => $s['editorial'],
                    'url' => $nul($s['url'] ?? null), 'fecha_consulta' => $fecha($s['fecha_consulta'] ?? null),
                ]);
            }

            if ($k = $nul($seo['palabra_clave_propuesta'] ?? null)) {
                ArticleKeyword::create(['article_id' => $a->id, 'keyword' => $k, 'rol' => 'principal', 'estado' => 'propuesta']);
            }
            foreach (($seo['palabras_clave_secundarias_propuestas'] ?? []) as $k) {
                ArticleKeyword::create(['article_id' => $a->id, 'keyword' => $k, 'rol' => 'secundaria', 'estado' => 'propuesta']);
            }

            ArticleStatusLog::create([
                'article_id' => $a->id,
                'estado_anterior' => null,
                'estado_nuevo' => $p['estado'],
                'usuario' => 'importación',
                'nota' => 'Importado desde blog/exportacion/blog_posts.json con el estado que traía.',
            ]);
        });

        return 'creado';
    }
}
