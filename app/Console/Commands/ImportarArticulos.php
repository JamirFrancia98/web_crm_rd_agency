<?php

namespace App\Console\Commands;

use App\Services\Blog\ArticleImporter;
use Illuminate\Console\Command;

class ImportarArticulos extends Command
{
    protected $signature = 'crm:importar-articulos {archivo : Ruta a blog/exportacion/blog_posts.json}';

    protected $description = 'Importa los artículos del paquete JSON del área de contenido (no pisa los que ya existen)';

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');
        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }

        $datos = json_decode((string) file_get_contents($ruta), true);
        if (! is_array($datos) || ! isset($datos['posts']) || ! is_array($datos['posts'])) {
            $this->error('El archivo no tiene el formato esperado (falta la lista «posts»).');

            return self::FAILURE;
        }

        $creados = $omitidos = 0;
        foreach ($datos['posts'] as $p) {
            $r = ArticleImporter::importar($p);
            $r === 'creado' ? $creados++ : $omitidos++;
            $this->line(sprintf('  %-8s %s', $r, ($p['id_tema'] ?? '?').' '.($p['contenido']['slug'] ?? '?')));
        }
        $this->info("Importación terminada: {$creados} creados, {$omitidos} omitidos (ya existían).");

        return self::SUCCESS;
    }
}
