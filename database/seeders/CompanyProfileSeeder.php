<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use Illuminate\Database\Seeder;

/**
 * Datos de la empresa CONFIRMADOS en docs/business/BUSINESS_PROFILE.md (2026-11-04).
 * Lo que no consta (dirección, URLs de redes) queda vacío: no se inventa. Solo crea la ficha si no existe.
 */
class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (CompanyProfile::query()->exists()) {
            return;
        }

        CompanyProfile::create([
            'nombre_comercial' => 'Revolution Digital',
            'razon_social' => 'REVOLUTION DIGITAL S.A.C',
            'ruc' => '20612802174',
            'responsable_datos' => 'REVOLUTION DIGITAL S.A.C',
            'correo_comercial' => 'contact@revolutiondigital.agency',
            'whatsapp' => '+51 913 466 762',
            'ciudad' => 'Lima, Perú',
            'horario' => '8:00–20:00 (GMT-5)',
            'web_url' => 'https://revolutiondigital.agency',
            'notas' => "Redes que existen según Jamir: Instagram, Facebook y LinkedIn (faltan las direcciones). X y YouTube no existen. TikTok pendiente.\n"
                .'Dirección registrada de la S.A.C. pendiente (necesaria para los textos legales). El dominio aún no recibe correo (MX nulo).',
        ]);
    }
}
