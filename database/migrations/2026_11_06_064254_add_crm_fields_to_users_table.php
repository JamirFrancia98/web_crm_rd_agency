<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // El rol por defecto es el de menos permisos.
            $table->string('role')->default('revisor')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            // Verificación en dos pasos (app autenticadora). Se guardan cifrados desde el modelo.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
