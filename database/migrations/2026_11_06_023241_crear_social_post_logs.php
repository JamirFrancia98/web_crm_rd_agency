<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de cada pieza de redes: quién la movió y cuándo (la aprobación es pieza por pieza, regla 6).
        Schema::create('social_post_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->string('usuario', 200);
            $table->string('nota', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_logs');
    }
};
