<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crea un administrador.
 * - Sin opciones: pide nombre, correo y contraseña por la terminal (la contraseña se escribe oculta).
 * - Con --generar: crea la cuenta con una contraseña aleatoria que se muestra UNA sola vez en esta terminal.
 *   Es para probar en local. Nunca se pasa la contraseña como argumento ni se guarda en archivos.
 */
class CrearAdmin extends Command
{
    protected $signature = 'crm:crear-admin
        {--generar : Genera una contraseña aleatoria y la muestra una vez (solo para pruebas locales)}
        {--nombre=Administrador : Nombre, con --generar}
        {--email= : Correo, con --generar}';

    protected $description = 'Crea un usuario administrador (por terminal o con contraseña generada para pruebas locales)';

    public function handle(): int
    {
        if ($this->option('generar')) {
            return $this->conContrasenaGenerada();
        }

        $name = text('Nombre', required: true);
        $email = text('Correo', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Correo no válido');

        if (User::where('email', $email)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $pass = password('Contraseña (mínimo 12 caracteres)', required: true, validate: fn (string $v) => strlen($v) >= 12 ? null : 'Mínimo 12 caracteres');

        $this->crear($name, $email, $pass);
        $this->info('Administrador creado. En el primer acceso el panel pedirá configurar la verificación en dos pasos.');

        return self::SUCCESS;
    }

    private function conContrasenaGenerada(): int
    {
        if (app()->isProduction()) {
            $this->error('--generar no se permite en producción.');

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?: 'admin@crm.test');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Correo no válido.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        $pass = Str::password(20, symbols: false);
        $this->crear((string) $this->option('nombre'), $email, $pass);

        $this->newLine();
        $this->info('Administrador creado (solo para pruebas locales). Anota estos datos ahora: la contraseña no se vuelve a mostrar.');
        $this->line("  Correo:     {$email}");
        $this->line("  Contraseña: {$pass}");
        $this->newLine();
        $this->warn('Cámbiala o crea tu propio usuario antes de usar el CRM con datos reales.');

        return self::SUCCESS;
    }

    private function crear(string $name, string $email, string $pass): void
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $pass]);
        $user->role = UserRole::Admin;
        $user->is_active = true;
        $user->save();
    }
}
