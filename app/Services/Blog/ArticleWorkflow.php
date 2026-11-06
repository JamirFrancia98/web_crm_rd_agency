<?php

namespace App\Services\Blog;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\ArticleStatusLog;
use App\Models\User;
use App\Support\BlogCatalog as C;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde cambia el estado de un artículo. Comprueba permisos y reglas y deja historial.
 * «Publicado» significa que el artículo ya está subido a la web; subirlo sigue siendo manual y aprobado por Jamir.
 */
final class ArticleWorkflow
{
    /** @return array<string, array{from: list<string>, to: string, roles: list<UserRole>, label: string, nota: bool}> */
    public static function acciones(): array
    {
        $todos = [UserRole::Admin, UserRole::Editor, UserRole::Revisor];
        $revisan = [UserRole::Admin, UserRole::Revisor];

        return [
            'enviar_a_revision' => ['from' => [C::BORRADOR], 'to' => C::EN_REVISION, 'roles' => $todos, 'label' => 'Enviar a revisión', 'nota' => false],
            'aprobar' => ['from' => [C::EN_REVISION], 'to' => C::APROBADO, 'roles' => $revisan, 'label' => 'Aprobar', 'nota' => false],
            'devolver_a_borrador' => ['from' => [C::EN_REVISION], 'to' => C::BORRADOR, 'roles' => $revisan, 'label' => 'Devolver a borrador', 'nota' => true],
            'retirar_aprobacion' => ['from' => [C::APROBADO], 'to' => C::EN_REVISION, 'roles' => $revisan, 'label' => 'Retirar aprobación', 'nota' => true],
            'publicar' => ['from' => [C::APROBADO], 'to' => C::PUBLICADO, 'roles' => [UserRole::Admin], 'label' => 'Marcar como publicado', 'nota' => false],
            // Para editar algo ya publicado: se retira la publicación, se edita, se vuelve a aprobar y a publicar.
            'despublicar' => ['from' => [C::PUBLICADO], 'to' => C::EN_REVISION, 'roles' => $revisan, 'label' => 'Despublicar para editar', 'nota' => true],
            'descartar' => ['from' => [C::BORRADOR, C::EN_REVISION, C::APROBADO], 'to' => C::DESCARTADO, 'roles' => $revisan, 'label' => 'Descartar', 'nota' => true],
            'reabrir' => ['from' => [C::DESCARTADO], 'to' => C::BORRADOR, 'roles' => [UserRole::Admin], 'label' => 'Reabrir como borrador', 'nota' => false],
        ];
    }

    public static function puede(Article $a, string $accion, User $user): bool
    {
        $def = self::acciones()[$accion] ?? null;

        return $def !== null
            && $user->is_active
            && in_array($a->estado, $def['from'], true)
            && $user->hasRole(...$def['roles']);
    }

    /**
     * @param  string|null  $nota  obligatoria en devolver, retirar y descartar.
     * @param  string|null  $fechaPublicacion  AAAA-MM-DD, obligatoria en publicar.
     *
     * @throws DomainException
     */
    public static function aplicar(Article $a, string $accion, User $user, ?string $nota = null, ?string $fechaPublicacion = null): void
    {
        $def = self::acciones()[$accion] ?? throw new DomainException('Acción desconocida.');

        if (! self::puede($a, $accion, $user)) {
            throw new DomainException("No puedes «{$def['label']}» este artículo (estado actual: ".C::etiquetaEstado($a->estado).", tu rol: {$user->role->label()}).");
        }
        $nota = $nota !== null ? trim($nota) : null;
        if ($def['nota'] && ($nota === null || $nota === '')) {
            throw new DomainException('Escribe el motivo: es obligatorio y queda en el historial.');
        }

        $a->loadMissing('sources');
        $hoy = now()->toDateString();
        $anterior = $a->estado;
        $cambios = [];

        switch ($accion) {
            case 'aprobar':
                // Quien escribe un artículo no lo aprueba (regla editorial). El administrador está exento.
                if ($a->created_by !== null && $a->created_by === $user->id && ! $user->isAdmin()) {
                    throw new DomainException('Quien escribe un artículo no lo aprueba: pídeselo a otra persona.');
                }
                $problemas = ArticleGate::problems($a, paraAprobar: true);
                if ($problemas) {
                    throw new DomainException("No se puede aprobar:\n- ".implode("\n- ", $problemas));
                }
                $cambios = ['fecha_aprobacion' => $hoy, 'fecha_revision' => $a->fecha_revision?->toDateString() ?? $hoy];
                if (blank($a->revisor)) {
                    $cambios['revisor'] = $user->name;
                }
                break;

            case 'publicar':
                if (! $fechaPublicacion || ! self::fechaValida($fechaPublicacion)) {
                    throw new DomainException('Indica la fecha de publicación (AAAA-MM-DD).');
                }
                // La portada es la miniatura al compartir en redes: sin ella no se marca como publicado.
                if (blank($a->imagen_src)) {
                    throw new DomainException('Falta la imagen de portada: es la miniatura que se ve al compartir el artículo en redes. Añádela (retirando antes la aprobación si hace falta).');
                }
                $a->fecha_publicacion = $fechaPublicacion; // para que la comprobación la vea
                $problemas = ArticleGate::problems($a);
                if ($problemas) {
                    throw new DomainException("No se puede marcar como publicado:\n- ".implode("\n- ", $problemas));
                }
                $cambios = ['fecha_publicacion' => $fechaPublicacion, 'robots' => 'index,follow'];
                break;

            case 'retirar_aprobacion':
                $cambios = ['fecha_aprobacion' => null, 'robots' => 'noindex,nofollow'];
                break;

            case 'despublicar':
                // Se conserva fecha_publicacion (la original); al volver a publicar se confirma la fecha.
                $cambios = ['fecha_aprobacion' => null, 'robots' => 'noindex,nofollow'];
                break;

            case 'descartar':
                $cambios = ['motivo_descarte' => $nota, 'robots' => 'noindex,nofollow'];
                break;

            case 'reabrir':
                $cambios = ['motivo_descarte' => null];
                break;

            case 'enviar_a_revision':
                $cambios = ['fecha_revision' => $a->fecha_revision?->toDateString() ?? $hoy];
                break;
        }

        DB::transaction(function () use ($a, $def, $cambios, $anterior, $user, $nota) {
            $a->forceFill($cambios + ['estado' => $def['to']])->save();
            ArticleStatusLog::create([
                'article_id' => $a->id,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $def['to'],
                'usuario' => $user->name.' <'.$user->email.'>',
                'nota' => $nota !== null && $nota !== '' ? mb_substr($nota, 0, 500) : null,
            ]);
        });
    }

    public static function puedeRevisionLegal(Article $a, User $user): bool
    {
        return $user->is_active && ! $a->isLocked() && $user->hasRole(UserRole::Admin, UserRole::Legal);
    }

    /**
     * Cambia la revisión legal. Solo el revisor legal o el administrador, con nota obligatoria, y queda en el historial.
     * No se puede en un artículo aprobado o publicado (hay que retirar antes la aprobación).
     *
     * @throws DomainException
     */
    public static function revisionLegal(Article $a, User $user, string $valor, string $nota): void
    {
        if (! array_key_exists($valor, C::REVISIONES_LEGALES)) {
            throw new DomainException('Valor de revisión legal no válido.');
        }
        if (! self::puedeRevisionLegal($a, $user)) {
            throw new DomainException('Solo el revisor legal o el administrador pueden marcar la revisión legal, y no en un artículo aprobado o publicado.');
        }
        $nota = trim($nota);
        if ($nota === '') {
            throw new DomainException('Escribe quién revisó y qué se decidió: queda en el historial.');
        }
        if ($valor === $a->revision_legal) {
            return;
        }

        DB::transaction(function () use ($a, $user, $valor, $nota) {
            $anterior = $a->revision_legal;
            Article::$cambioLegalAutorizado = true;
            try {
                $a->forceFill(['revision_legal' => $valor])->save();
            } finally {
                Article::$cambioLegalAutorizado = false;
            }
            ArticleStatusLog::create([
                'article_id' => $a->id,
                'estado_anterior' => 'legal: '.$anterior,
                'estado_nuevo' => 'legal: '.$valor,
                'usuario' => $user->name.' <'.$user->email.'>',
                'nota' => mb_substr($nota, 0, 500),
            ]);
        });
    }

    private static function fechaValida(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && gmdate('Y-m-d', strtotime($v.' 00:00:00 UTC')) === $v;
    }
}
