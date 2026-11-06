<?php

namespace App\Support;

/**
 * Valores permitidos del blog. Deben coincidir con los de la web
 * (rd-astro: src/lib/blog-estados.js y src/content.config.js). Si la web cambia, se cambia aquí también.
 */
final class BlogCatalog
{
    public const BORRADOR = 'borrador';
    public const EN_REVISION = 'en revisión';
    public const APROBADO = 'aprobado';
    public const PUBLICADO = 'publicado';
    public const DESCARTADO = 'descartado';

    /** @var array<string, string> */
    public const ESTADOS = [
        self::BORRADOR => 'Borrador',
        self::EN_REVISION => 'En revisión',
        self::APROBADO => 'Aprobado',
        self::PUBLICADO => 'Publicado',
        self::DESCARTADO => 'Descartado',
    ];

    /** Estados que la web compila (los demás nunca salen). */
    public const COMPILABLES = [self::APROBADO, self::PUBLICADO];

    public const IDIOMAS = ['es' => 'Español', 'en' => 'English', 'it' => 'Italiano'];

    public const CATEGORIAS = [
        'diseno-web' => 'Diseño web',
        'hosting' => 'Hosting',
        'software' => 'Software',
        'igaming' => 'iGaming',
        'automatizacion-ia' => 'Automatización e IA',
        'seo' => 'SEO',
        'negocio' => 'Negocio',
    ];

    public const TIPOS = [
        'guia' => 'Guía',
        'explicativo' => 'Explicativo',
        'comparativa' => 'Comparativa',
        'noticia' => 'Noticia',
        'experiencia-propia' => 'Experiencia propia',
        'caso' => 'Caso',
    ];

    public const INTENCIONES = [
        'informativa' => 'Informativa',
        'comparación' => 'Comparación',
        'decisión' => 'Decisión',
    ];

    public const RIESGOS = [
        'ninguno' => 'Ninguno',
        'igaming' => 'iGaming',
        'publicidad-juego' => 'Publicidad de juego',
        'datos-personales' => 'Datos personales',
        'cifras' => 'Cifras',
        'regulacion' => 'Regulación',
    ];

    public const REVISIONES_LEGALES = [
        'no-requerida' => 'No requerida',
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
    ];

    /** Páginas de servicio de la web a las que puede enlazar un artículo (siempre la ruta en español). */
    public const SERVICIOS = [
        '/diseno-web/' => 'Diseño web',
        '/software/' => 'Software a medida',
        '/hosting/' => 'Hosting',
        '/automatizacion-ia/' => 'Automatización e IA',
        '/igaming/' => 'iGaming',
        '/contacto/' => 'Contacto',
    ];

    public static function etiquetaEstado(string $estado): string
    {
        return self::ESTADOS[$estado] ?? $estado;
    }
}
