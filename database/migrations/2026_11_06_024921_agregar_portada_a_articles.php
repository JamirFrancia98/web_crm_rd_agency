<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una sola imagen por artículo: sirve de banner en la web y de miniatura al compartir en redes.
        Schema::table('articles', function (Blueprint $table) {
            $table->string('imagen_archivo', 300)->nullable()->after('imagen_src'); // ruta en el disco «public» del CRM
            $table->unsignedInteger('imagen_ancho')->nullable()->after('imagen_archivo');
            $table->unsignedInteger('imagen_alto')->nullable()->after('imagen_ancho');
            $table->unsignedInteger('imagen_bytes')->nullable()->after('imagen_alto');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['imagen_archivo', 'imagen_ancho', 'imagen_alto', 'imagen_bytes']);
        });
    }
};
