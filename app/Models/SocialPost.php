<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPost extends Model
{
    /** `estado` y la aprobación no se asignan en masa: solo cambian por SocialWorkflow. */
    protected $guarded = ['id', 'estado', 'aprobado_por', 'aprobado_en'];

    public const ESTADOS = [
        'por redactar' => 'Por redactar',
        'borrador' => 'Borrador',
        'en revisión' => 'En revisión',
        'aprobada' => 'Aprobada',
        'programada' => 'Programada',
        'publicada' => 'Publicada',
        'descartada' => 'Descartada',
    ];

    /** Estados en los que el texto aprobado ya no se toca. */
    public const BLOQUEADOS = ['aprobada', 'programada', 'publicada'];

    private const EDITABLES_BLOQUEADO = ['estado', 'aprobado_por', 'aprobado_en', 'enlace', 'notas', 'updated_at'];

    protected $attributes = ['estado' => 'por redactar', 'red' => 'linkedin'];

    protected function casts(): array
    {
        return ['fecha_propuesta' => 'date', 'aprobado_en' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Lo aprobado es lo que Jamir revisó: el texto no cambia sin devolverlo antes a revisión.
        static::updating(function (SocialPost $p) {
            if (! in_array($p->getOriginal('estado'), self::BLOQUEADOS, true)) {
                return;
            }
            $cambios = array_diff(array_keys($p->getDirty()), self::EDITABLES_BLOQUEADO);
            if ($cambios) {
                throw new DomainException('La pieza está '.$p->getOriginal('estado').' y no se puede editar. Retira la aprobación primero (cambia: '.implode(', ', $cambios).').');
            }
        });
    }

    public function isLocked(): bool
    {
        return in_array($this->estado, self::BLOQUEADOS, true);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SocialPostLog::class)->orderByDesc('id');
    }
}
