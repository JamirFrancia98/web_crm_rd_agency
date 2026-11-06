<?php

namespace App\Enums;

/**
 * Roles del CRM.
 * - Admin: todo, incluida la gestión de usuarios y los datos de la empresa.
 * - Editor: escribe artículos y gestiona clientes y mensajes; no aprueba ni publica.
 * - Revisor: revisa y aprueba artículos; solo lectura en el resto.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Revisor = 'revisor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Editor => 'Editor',
            self::Revisor => 'Revisor',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $r) {
            $out[$r->value] = $r->label();
        }

        return $out;
    }
}
