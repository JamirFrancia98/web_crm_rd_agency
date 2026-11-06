<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessageLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Registra una acción sin copiar datos personales. */
    public static function anotar(?int $mensajeId, string $accion, string $usuario, ?string $nota = null): void
    {
        static::create(['contact_message_id' => $mensajeId, 'accion' => $accion, 'usuario' => $usuario, 'nota' => $nota]);
    }
}
