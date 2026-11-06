<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos de la empresa (una sola fila). Nada se inventa: lo que no consta queda vacío o PENDIENTE. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_comercial');
            $table->string('razon_social');
            $table->string('ruc', 20)->nullable();
            $table->string('responsable_datos')->nullable();
            $table->string('correo_comercial')->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('ciudad')->nullable();
            $table->string('horario')->nullable();
            $table->string('direccion')->nullable();
            $table->string('web_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
