<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mensaje del formulario de contacto: dato personal. Se muestra siempre como texto, nunca como HTML. */
class ContactMessage extends Model
{
    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'leído' => 'Leído',
        'respondido' => 'Respondido',
        'archivado' => 'Archivado',
        'spam' => 'Spam',
    ];

    /** `estado` solo cambia desde el panel (con registro de quién lo hizo). */
    protected $guarded = ['id', 'estado'];

    protected $attributes = ['estado' => 'nuevo', 'origen' => 'formulario', 'idioma' => 'es'];

    protected function casts(): array
    {
        return ['consentimiento' => 'boolean', 'consentimiento_en' => 'datetime', 'conservar_hasta' => 'date'];
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ContactMessageLog::class)->orderByDesc('id');
    }
}
