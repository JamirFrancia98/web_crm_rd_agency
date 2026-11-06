<?php

namespace App\Console\Commands;

use App\Services\Social\SocialImporter;
use Illuminate\Console\Command;

class ImportarRedes extends Command
{
    protected $signature = 'crm:importar-redes {carpeta : Ruta a la carpeta social/linkedin del área de contenido}';

    protected $description = 'Importa las piezas y el calendario de LinkedIn (no pisa las que ya existen)';

    public function handle(): int
    {
        $carpeta = (string) $this->argument('carpeta');
        if (! is_dir($carpeta.'/piezas')) {
            $this->error("No encuentro {$carpeta}/piezas");

            return self::FAILURE;
        }

        $r = SocialImporter::importar($carpeta);
        foreach ($r as $ref => $res) {
            $this->line(sprintf('  %-8s %s', $res, $ref));
        }
        $this->info('Redes: '.count(array_filter($r, fn ($x) => $x === 'creada')).' creadas, '.count(array_filter($r, fn ($x) => $x === 'omitida')).' omitidas (ya existían).');

        return self::SUCCESS;
    }
}
