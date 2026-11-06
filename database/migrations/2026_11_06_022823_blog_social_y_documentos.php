<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vista previa al compartir en redes: título y descripción propios (si no, se usan los SEO) y quién creó el artículo.
        Schema::table('articles', function (Blueprint $table) {
            $table->string('og_title', 120)->nullable()->after('meta_description');
            $table->string('og_description', 300)->nullable()->after('og_title');
            $table->foreignId('created_by')->nullable()->after('archivo_origen')->constrained('users')->nullOnDelete();
        });

        // Piezas de redes sociales (hoy LinkedIn). Origen: social/linkedin/ de la carpeta de negocio.
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 16)->unique();                 // LI-01
            $table->string('red', 20)->default('linkedin');
            $table->string('titulo', 200);
            $table->string('estado', 20)->default('por redactar')->index();
            $table->date('fecha_propuesta')->nullable();
            $table->string('formato', 200)->nullable();
            $table->string('pilar', 200)->nullable();
            $table->string('fuente_interna', 300)->nullable();
            $table->string('enlace', 300)->nullable();
            $table->string('aprobado_por', 120)->nullable();     // quién aprobó la pieza (regla 6: pieza por pieza)
            $table->timestamp('aprobado_en')->nullable();
            $table->text('notas')->nullable();
            $table->longText('body_md')->nullable();
            $table->string('archivo_origen', 200)->nullable();
            $table->timestamps();
        });

        // Biblioteca de documentos de contenido (solo lectura). Copia de archivos Markdown de una lista cerrada.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('ruta', 250)->unique();               // relativa a la carpeta de negocio
            $table->string('titulo', 250);
            $table->string('categoria', 30)->index();            // blog | redes | web | portafolio | servicios | marketing
            $table->longText('contenido_md');
            $table->string('hash', 64);
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('social_posts');
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['og_title', 'og_description']);
        });
    }
};
