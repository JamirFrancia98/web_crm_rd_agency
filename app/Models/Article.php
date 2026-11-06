<?php

namespace App\Models;

use App\Support\BlogCatalog;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * `estado` no se asigna en masa: solo cambia por ArticleWorkflow, que comprueba permisos y reglas y deja historial.
     */
    protected $guarded = ['id', 'estado'];

    /** Mismos valores por defecto que la base de datos, para que un artículo recién creado en memoria sea coherente. */
    protected $attributes = [
        'estado' => 'borrador',
        'tipo' => 'guia',
        'riesgo_legal' => 'ninguno',
        'revision_legal' => 'no-requerida',
        'keyword_estado' => 'propuesta',
        'robots' => 'noindex,nofollow',
        'autor' => 'Revolution Digital',
        'generado_con_ia' => true,
        'cifras_propias' => false,
        'menciona_clientes' => false,
    ];

    /** Campos que pueden cambiar aunque el artículo esté aprobado o publicado (los mueve el flujo). */
    private const EDITABLES_BLOQUEADO = [
        'estado', 'fecha_revision', 'fecha_aprobacion', 'fecha_publicacion', 'revisor',
        'motivo_descarte', 'robots', 'nota_revisor', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'generado_con_ia' => 'boolean',
            'cifras_propias' => 'boolean',
            'menciona_clientes' => 'boolean',
            'fecha_borrador' => 'date',
            'fecha_revision' => 'date',
            'fecha_aprobacion' => 'date',
            'fecha_publicacion' => 'date',
        ];
    }

    /** Lo activa solo ArticleWorkflow::revisionLegal(): la revisión legal no se cambia por ningún otro camino. */
    public static bool $cambioLegalAutorizado = false;

    protected static function booted(): void
    {
        static::updating(function (Article $a) {
            if ($a->isDirty('revision_legal') && ! self::$cambioLegalAutorizado) {
                throw new DomainException('La revisión legal solo la marca el revisor legal o el administrador, desde la acción del artículo.');
            }
        });

        // Lo aprobado es lo que se revisó: no se cambia el contenido sin devolverlo antes a revisión.
        static::updating(function (Article $a) {
            if (! in_array($a->getOriginal('estado'), BlogCatalog::COMPILABLES, true)) {
                return;
            }
            $cambios = array_diff(array_keys($a->getDirty()), self::EDITABLES_BLOQUEADO);
            if ($cambios) {
                throw new DomainException('El artículo está '.$a->getOriginal('estado').' y no se puede editar. Devuélvelo a revisión primero (cambia: '.implode(', ', $cambios).').');
            }
        });
    }

    public function creador(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ArticleSource::class)->orderBy('orden');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(ArticleKeyword::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ArticleStatusLog::class)->orderByDesc('id');
    }

    public function isLocked(): bool
    {
        return in_array($this->estado, BlogCatalog::COMPILABLES, true);
    }
}
