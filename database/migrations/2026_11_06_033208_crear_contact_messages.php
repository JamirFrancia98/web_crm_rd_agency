<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mensajes del formulario de contacto. Son datos personales (regla 7): solo se guardan con el consentimiento
        // registrado, y no se guarda la IP. El plazo de conservación lo fija el abogado (config inbox.retention_days).
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('empresa', 160)->nullable();
            $table->string('email', 160)->index();
            $table->string('telefono', 40)->nullable();
            $table->string('servicio', 80)->nullable();
            $table->string('presupuesto', 80)->nullable();
            $table->text('mensaje');
            $table->string('idioma', 2)->default('es');
            $table->string('pagina', 200)->nullable();
            $table->string('origen', 20)->default('formulario');
            $table->string('estado', 20)->default('nuevo')->index(); // nuevo | leído | respondido | archivado | spam
            $table->boolean('consentimiento')->default(false);
            $table->string('consentimiento_version', 40)->nullable(); // qué versión del aviso de privacidad se mostró
            $table->timestamp('consentimiento_en')->nullable();
            $table->date('conservar_hasta')->nullable();
            $table->text('notas')->nullable();                        // notas internas
            $table->timestamps();
        });

        // Quién vio o cambió cada mensaje. Sin datos personales: sobrevive al borrado del mensaje.
        Schema::create('contact_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_message_id')->nullable()->index();
            $table->string('accion', 40);
            $table->string('usuario', 200);
            $table->string('nota', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_logs');
        Schema::dropIfExists('contact_messages');
    }
};
