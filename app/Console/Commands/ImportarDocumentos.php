<?php

namespace App\Console\Commands;

use App\Services\Docs\DocumentImporter;
use Illuminate\Console\Command;

class ImportarDocumentos extends Command
{
    protected $signature = 'crm:importar-documentos {raiz : Carpeta de negocio (Revolution Digital)}';

    protected $description = 'Copia (solo lectura) los documentos de contenido de una lista cerrada a la biblioteca del CRM';

    public function handle(): int
    {
        $raiz = (string) $this->argument('raiz');
        if (! is_dir($raiz)) {
            $this->error("No existe la carpeta: {$raiz}");

            return self::FAILURE;
        }

        $r = DocumentImporter::importar($raiz);
        foreach ($r as $ruta => $res) {
            $this->line(sprintf('  %-11s %s', $res, $ruta));
        }
        $cuenta = array_count_values($r);
        $this->info('Documentos: '.($cuenta['creado'] ?? 0).' creados, '.($cuenta['actualizado'] ?? 0).' actualizados, '.($cuenta['igual'] ?? 0).' sin cambios.');

        return self::SUCCESS;
    }
}
