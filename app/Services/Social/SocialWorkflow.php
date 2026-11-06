<?php

namespace App\Services\Social;

use App\Enums\UserRole;
use App\Models\SocialPost;
use App\Models\SocialPostLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde cambia el estado de una pieza de redes. «Programada» y «Publicada» solo REGISTRAN lo que
 * ya se hizo a mano en la red: el CRM no publica nada ni conecta con ninguna red (regla 6).
 */
final class SocialWorkflow
{
    /** @return array<string, array{from: list<string>, to: string, roles: list<UserRole>, label: string, nota: bool}> */
    public static function acciones(): array
    {
        $todos = [UserRole::Admin, UserRole::Editor, UserRole::Revisor];
        $revisan = [UserRole::Admin, UserRole::Revisor];
        $admin = [UserRole::Admin];

        return [
            'empezar_borrador' => ['from' => ['por redactar'], 'to' => 'borrador', 'roles' => $todos, 'label' => 'Empezar borrador', 'nota' => false],
            'enviar_a_revision' => ['from' => ['borrador'], 'to' => 'en revisión', 'roles' => $todos, 'label' => 'Enviar a revisión', 'nota' => false],
            'aprobar' => ['from' => ['en revisión'], 'to' => 'aprobada', 'roles' => $revisan, 'label' => 'Aprobar el texto', 'nota' => false],
            'devolver_a_borrador' => ['from' => ['en revisión'], 'to' => 'borrador', 'roles' => $revisan, 'label' => 'Devolver a borrador', 'nota' => true],
            'retirar_aprobacion' => ['from' => ['aprobada', 'programada'], 'to' => 'en revisión', 'roles' => $revisan, 'label' => 'Retirar aprobación', 'nota' => true],
            'programar' => ['from' => ['aprobada'], 'to' => 'programada', 'roles' => $admin, 'label' => 'Registrar como programada', 'nota' => false],
            'publicar' => ['from' => ['aprobada', 'programada'], 'to' => 'publicada', 'roles' => $admin, 'label' => 'Registrar como publicada', 'nota' => false],
            'descartar' => ['from' => ['por redactar', 'borrador', 'en revisión', 'aprobada', 'programada'], 'to' => 'descartada', 'roles' => $revisan, 'label' => 'Descartar', 'nota' => true],
            'reabrir' => ['from' => ['descartada'], 'to' => 'borrador', 'roles' => $admin, 'label' => 'Reabrir como borrador', 'nota' => false],
        ];
    }

    public static function puede(SocialPost $p, string $accion, User $user): bool
    {
        $def = self::acciones()[$accion] ?? null;

        return $def !== null
            && $user->is_active
            && in_array($p->estado, $def['from'], true)
            && $user->hasRole(...$def['roles']);
    }

    /** @throws DomainException */
    public static function aplicar(SocialPost $p, string $accion, User $user, ?string $nota = null): void
    {
        $def = self::acciones()[$accion] ?? throw new DomainException('Acción desconocida.');

        if (! self::puede($p, $accion, $user)) {
            throw new DomainException("No puedes «{$def['label']}» esta pieza (estado actual: {$p->estado}, tu rol: {$user->role->label()}).");
        }
        $nota = $nota !== null ? trim($nota) : null;
        if ($def['nota'] && ($nota === null || $nota === '')) {
            throw new DomainException('Escribe el motivo: es obligatorio y queda en el historial.');
        }

        $cambios = [];
        switch ($accion) {
            case 'enviar_a_revision':
                if (blank($p->body_md)) {
                    throw new DomainException('La pieza no tiene texto todavía.');
                }
                break;
            case 'aprobar':
                if (blank($p->body_md)) {
                    throw new DomainException('No se aprueba una pieza sin texto.');
                }
                $cambios = ['aprobado_por' => $user->name, 'aprobado_en' => now()];
                break;
            case 'retirar_aprobacion':
            case 'devolver_a_borrador':
            case 'descartar':
                $cambios = ['aprobado_por' => null, 'aprobado_en' => null];
                break;
        }

        $anterior = $p->estado;
        DB::transaction(function () use ($p, $def, $cambios, $anterior, $user, $nota) {
            $p->forceFill($cambios + ['estado' => $def['to']])->save();
            SocialPostLog::create([
                'social_post_id' => $p->id,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $def['to'],
                'usuario' => $user->name.' <'.$user->email.'>',
                'nota' => $nota !== null && $nota !== '' ? mb_substr($nota, 0, 500) : null,
            ]);
        });
    }
}
