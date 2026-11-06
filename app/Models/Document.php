<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Copia de solo lectura de un documento Markdown de contenido. Se actualiza con `crm:importar-documentos`. */
class Document extends Model
{
    protected $guarded = ['id'];

    public const CATEGORIAS = [
        'blog' => 'Blog',
        'redes' => 'Redes sociales',
        'web' => 'Web',
        'portafolio' => 'Portafolio',
        'servicios' => 'Servicios',
        'marketing' => 'Marketing',
    ];

    protected function casts(): array
    {
        return ['sincronizado_en' => 'datetime'];
    }
}
