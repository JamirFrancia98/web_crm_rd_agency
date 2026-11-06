<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\Blog\ArticleMarkdown;
use App\Support\BlogCatalog;
use Illuminate\Console\Command;

class ExportarArticulos extends Command
{
    protected $signature = 'crm:exportar-articulos {carpeta : Carpeta de destino (se crea si no existe)}';

    protected $description = 'Exporta a Markdown todos los artículos aprobados o publicados, con el formato que lee la web';

    public function handle(): int
    {
        $carpeta = rtrim((string) $this->argument('carpeta'), '/');
        if (! is_dir($carpeta) && ! mkdir($carpeta, 0755, true)) {
            $this->error("No se pudo crear la carpeta: {$carpeta}");

            return self::FAILURE;
        }

        $n = 0;
        foreach (Article::with('sources')->whereIn('estado', BlogCatalog::COMPILABLES)->orderBy('id')->get() as $a) {
            $out = ArticleMarkdown::export($a);
            file_put_contents($carpeta.'/'.$out['filename'], $out['content']);
            $this->line('  '.$out['filename']);
            $n++;
        }
        $this->info("{$n} artículos exportados a {$carpeta}. Copiarlos a la web y publicarlos sigue siendo un paso manual aprobado por Jamir.");

        return self::SUCCESS;
    }
}
