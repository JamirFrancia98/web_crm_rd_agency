<?php

namespace App\Services\Docs;

use App\Models\Document;

/**
 * Copia de SOLO LECTURA de documentos Markdown de contenido, de una lista cerrada de rutas.
 * Nunca entra nada fuera de la lista: ni «Cosas de A51/», ni contrataciones, ni credenciales, ni fichas de clientes (reglas 7 y 8).
 */
final class DocumentImporter
{
    /** Ruta relativa (o patrón con *) => categoría. */
    public const LISTA = [
        'blog/COLA.md' => 'blog',
        'blog/exportacion/LEEME.md' => 'blog',
        'docs/blog/*.md' => 'blog',
        'social/linkedin/CALENDARIO.md' => 'redes',
        'docs/marketing/*.md' => 'marketing',
        'docs/web/*.md' => 'web',
        'docs/services/SERVICES.md' => 'servicios',
        'portfolio/README.md' => 'portafolio',
    ];

    private const MAX_BYTES = 400_000;

    /** @return array<string, 'creado'|'actualizado'|'igual'> ruta => resultado */
    public static function importar(string $raiz): array
    {
        $raiz = rtrim($raiz, '/');
        $real = realpath($raiz);
        $out = [];
        if ($real === false) {
            return $out;
        }

        foreach (self::LISTA as $patron => $categoria) {
            foreach (glob($real.'/'.$patron) ?: [] as $archivo) {
                $ruta = ltrim(substr($archivo, strlen($real)), '/');
                // Defensa extra: el archivo resuelto debe seguir dentro de la raíz y ser Markdown pequeño.
                $resuelto = realpath($archivo);
                if ($resuelto === false || ! str_starts_with($resuelto, $real.'/') || ! str_ends_with($resuelto, '.md')
                    || filesize($resuelto) > self::MAX_BYTES || self::prohibida($ruta)) {
                    continue;
                }
                $out[$ruta] = self::guardar($ruta, $categoria, (string) file_get_contents($resuelto));
            }
        }
        ksort($out);

        return $out;
    }

    public static function prohibida(string $ruta): bool
    {
        return (bool) preg_match('#(^|/)(Cosas de A51|contrataciones?|operations|sales)(/|$)|FICHA\.md$|\.env#i', $ruta);
    }

    private static function guardar(string $ruta, string $categoria, string $md): string
    {
        $hash = hash('sha256', $md);
        $doc = Document::where('ruta', $ruta)->first();
        if ($doc && $doc->hash === $hash) {
            return 'igual';
        }
        $titulo = preg_match('/^#\s+(.+)$/m', $md, $t) ? trim($t[1]) : basename($ruta, '.md');
        $datos = ['titulo' => mb_substr($titulo, 0, 250), 'categoria' => $categoria, 'contenido_md' => $md, 'hash' => $hash, 'sincronizado_en' => now()];

        if ($doc) {
            $doc->update($datos);

            return 'actualizado';
        }
        Document::create($datos + ['ruta' => $ruta]);

        return 'creado';
    }
}
