<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de artículos. Basado en la propuesta de esquema del área de contenido
 * (blog/exportacion/crm_blog_schema.sql), adaptado a Laravel. Los valores permitidos se validan en PHP
 * (App\Support\BlogCatalog) para que el mismo código sirva en SQLite (pruebas) y MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('post_ref', 16)->unique();           // ej. T01-es (clave de importación)
            $table->string('id_tema', 8)->nullable();
            $table->string('grupo_traduccion')->nullable();
            $table->string('idioma', 2);
            $table->string('slug', 120);
            $table->string('titulo', 200);
            $table->string('descripcion', 300);                  // frontmatter `descripcion` de la web
            $table->string('seo_title', 120)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('estado', 20)->default('borrador')->index();
            $table->string('categoria', 30);
            $table->string('tipo', 30)->default('guia');
            $table->string('intencion_busqueda', 30)->nullable();
            $table->string('servicio_relacionado', 120)->nullable();   // null = PENDIENTE
            $table->string('keyword_oficial', 160)->nullable();        // null = PENDIENTE; la fija Web y SEO
            $table->string('keyword_estado', 20)->default('propuesta');
            $table->string('canonical_path', 200)->nullable();
            $table->string('canonical_url', 300)->nullable();
            $table->string('robots', 40)->default('noindex,nofollow'); // index,follow solo al publicar
            $table->string('imagen_src', 300)->nullable();
            $table->string('imagen_alt', 200)->nullable();
            $table->string('imagen_licencia', 200)->nullable();
            $table->string('autor', 80)->default('Revolution Digital');
            $table->string('revisor', 80)->nullable();
            $table->boolean('generado_con_ia')->default(true);
            $table->string('riesgo_legal', 30)->default('ninguno');
            $table->string('revision_legal', 20)->default('no-requerida');
            $table->boolean('cifras_propias')->default(false);
            $table->text('evidencia')->nullable();
            $table->boolean('menciona_clientes')->default(false);
            $table->text('aporte_propio')->nullable();
            $table->text('nota_revisor')->nullable();           // interna, nunca se publica
            $table->date('fecha_borrador')->nullable();
            $table->date('fecha_revision')->nullable();
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_publicacion')->nullable();
            $table->text('motivo_descarte')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('archivo_origen', 200)->nullable();
            $table->longText('body_md');
            $table->timestamps();

            $table->unique(['idioma', 'slug']);
        });

        Schema::create('article_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('orden')->default(1);
            $table->string('titulo', 300);
            $table->string('editorial', 200);
            $table->string('url', 500)->nullable();
            $table->date('fecha_consulta')->nullable();
            $table->timestamps();
        });

        Schema::create('article_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 160);
            $table->string('rol', 12)->default('secundaria');      // principal | secundaria
            $table->string('estado', 12)->default('propuesta');    // propuesta | validada
            $table->unsignedInteger('volumen_mensual')->nullable(); // solo con fuente; nunca estimado
            $table->string('fuente_volumen', 160)->nullable();
            $table->date('fecha_validacion')->nullable();
            $table->string('validado_por', 80)->nullable();
            $table->timestamps();
        });

        // Historial: quién cambió el estado, cuándo y con qué nota. Sin FK a users para que sobreviva a cambios de usuario.
        Schema::create('article_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->string('usuario', 120);
            $table->string('nota', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_status_logs');
        Schema::dropIfExists('article_keywords');
        Schema::dropIfExists('article_sources');
        Schema::dropIfExists('articles');
    }
};
