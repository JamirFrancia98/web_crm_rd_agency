<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Datos de la empresa. Hay una sola fila: se obtiene con CompanyProfile::singleton(). */
class CompanyProfile extends Model
{
    protected $guarded = ['id'];

    public static function singleton(): self
    {
        return self::query()->first() ?? self::create([
            'nombre_comercial' => 'Revolution Digital',
            'razon_social' => 'PENDIENTE',
        ]);
    }
}
