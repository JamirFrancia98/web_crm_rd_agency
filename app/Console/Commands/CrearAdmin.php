<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crea el primer administrador. La contraseña se escribe en la terminal (oculta); nunca se pasa como argumento
 * ni se guarda en archivos ni en el historial.
 */
class CrearAdmin extends Command
{
    protected $signature = 'crm:crear-admin';

    protected $description = 'Crea un usuario administrador pidiendo nombre, correo y contraseña por la terminal';

    public function handle(): int
    {
        $name = text('Nombre', required: true);
        $email = text('Correo', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Correo no válido');

        if (User::where('email', $email)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $pass = password('Contraseña (mínimo 12 caracteres)', required: true, validate: fn (string $v) => strlen($v) >= 12 ? null : 'Mínimo 12 caracteres');

        $user = new User(['name' => $name, 'email' => $email, 'password' => $pass]);
        $user->role = UserRole::Admin;
        $user->is_active = true;
        $user->save();

        $this->info('Administrador creado. En el primer acceso el panel pedirá configurar la verificación en dos pasos.');

        return self::SUCCESS;
    }
}
