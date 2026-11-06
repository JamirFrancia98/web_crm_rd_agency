<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use App\Models\ContactMessageLog;
use Illuminate\Console\Command;

class PurgarMensajes extends Command
{
    protected $signature = 'crm:purgar-mensajes';

    protected $description = 'Elimina los mensajes cuyo plazo de conservación ya venció (solo si hay plazo definido)';

    public function handle(): int
    {
        if (config('inbox.retention_days') === null) {
            $this->warn('No hay plazo de conservación definido (INBOX_RETENTION_DAYS): no se borra nada. Lo fija el abogado.');

            return self::SUCCESS;
        }

        $n = 0;
        foreach (ContactMessage::whereNotNull('conservar_hasta')->where('conservar_hasta', '<', now()->toDateString())->get() as $m) {
            $id = $m->id;
            $m->delete();
            ContactMessageLog::anotar($id, 'eliminado por plazo', 'sistema', 'Venció el plazo de conservación.');
            $n++;
        }
        $this->info("{$n} mensajes eliminados por plazo vencido.");

        return self::SUCCESS;
    }
}
