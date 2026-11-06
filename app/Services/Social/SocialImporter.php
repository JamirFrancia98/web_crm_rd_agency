<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use App\Models\SocialPostLog;

/**
 * Importa las piezas de LinkedIn desde social/linkedin/ (piezas/*.md + CALENDARIO.md).
 * El estado y la aprobación se copian tal cual constan en los archivos (hecho documentado, no decisión nueva).
 * Si la pieza ya existe en el CRM NO se toca.
 */
final class SocialImporter
{
    /** @return array<string, string> ref => 'creada'|'omitida' */
    public static function importar(string $carpetaLinkedin): array
    {
        $carpeta = rtrim($carpetaLinkedin, '/');
        $resultado = [];
        $calendario = is_file($carpeta.'/CALENDARIO.md') ? self::leerCalendario((string) file_get_contents($carpeta.'/CALENDARIO.md')) : [];

        $archivos = glob($carpeta.'/piezas/*.md') ?: [];
        sort($archivos);
        foreach ($archivos as $archivo) {
            $p = self::leerPieza((string) file_get_contents($archivo));
            if ($p === null) {
                continue;
            }
            $ref = $p['ref'];
            $resultado[$ref] = self::crear($ref, [
                'titulo' => $p['titulo'] ?: ($calendario[$ref]['titulo'] ?? $ref),
                'fecha_propuesta' => $p['fecha_propuesta'] ?? ($calendario[$ref]['fecha'] ?? null),
                'formato' => $p['formato'], 'pilar' => $p['pilar'], 'fuente_interna' => $p['fuente_interna'], 'enlace' => $p['enlace'],
                'body_md' => $p['body'], 'archivo_origen' => 'social/linkedin/piezas/'.basename($archivo),
            ], $p['estado'], $p['aprobado_en']);
        }

        // Piezas del calendario que aún no tienen archivo (se crean «por redactar»).
        foreach ($calendario as $ref => $c) {
            if (! isset($resultado[$ref])) {
                $resultado[$ref] = self::crear($ref, [
                    'titulo' => $c['titulo'], 'fecha_propuesta' => $c['fecha'], 'notas' => $c['nota'],
                ], 'por redactar', null);
            }
        }

        ksort($resultado);

        return $resultado;
    }

    private static function crear(string $ref, array $datos, string $estado, ?string $aprobadoEn): string
    {
        if (SocialPost::where('ref', $ref)->exists()) {
            return 'omitida';
        }
        $post = new SocialPost($datos + ['ref' => $ref, 'red' => 'linkedin']);
        $post->forceFill(['estado' => $estado]);
        if ($aprobadoEn !== null) {
            $post->forceFill(['aprobado_por' => 'Jamir (según el archivo de origen)', 'aprobado_en' => $aprobadoEn.' 00:00:00']);
        }
        $post->save();
        SocialPostLog::create([
            'social_post_id' => $post->id, 'estado_anterior' => null, 'estado_nuevo' => $estado,
            'usuario' => 'importación', 'nota' => 'Importada desde los archivos de contenido con el estado que ya tenía.',
        ]);

        return 'creada';
    }

    /** @return array{ref: string, titulo: string, estado: string, fecha_propuesta: ?string, formato: ?string, pilar: ?string, fuente_interna: ?string, enlace: ?string, aprobado_en: ?string, body: string}|null */
    public static function leerPieza(string $md): ?array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $md, $m)) {
            return null;
        }
        $f = [];
        foreach (preg_split('/\R/', $m[1]) as $linea) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', $linea, $kv)) {
                $f[$kv[1]] = trim(preg_replace('/\s+#\s.*$/', '', $kv[2]));
            }
        }
        if (empty($f['id'])) {
            return null;
        }
        $body = trim($m[2]);
        $titulo = '';
        if (preg_match('/^#\s+'.preg_quote($f['id'], '/').'\s*·\s*(.+)$/m', $body, $t)) {
            $titulo = trim($t[1]);
        }
        $estado = array_key_exists($f['estado'] ?? '', SocialPost::ESTADOS) ? $f['estado'] : 'borrador';
        $vacio = fn (?string $v) => ($v === null || $v === '' || mb_strtolower($v) === 'ninguno' || str_starts_with($v, 'PENDIENTE')) ? null : $v;
        $aprobado = null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $f['aprobacion_jamir'] ?? '', $a)) {
            $aprobado = $a[1];
        }

        return [
            'ref' => $f['id'], 'titulo' => $titulo, 'estado' => $estado,
            'fecha_propuesta' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['fecha_propuesta'] ?? '') ? $f['fecha_propuesta'] : null,
            'formato' => $vacio($f['formato'] ?? null), 'pilar' => $vacio($f['pilar'] ?? null),
            'fuente_interna' => $vacio($f['fuente_interna'] ?? null), 'enlace' => $vacio($f['enlace'] ?? null),
            'aprobado_en' => in_array($estado, SocialPost::BLOQUEADOS, true) ? $aprobado : null,
            'body' => $body,
        ];
    }

    /** @return array<string, array{fecha: string, titulo: string, nota: ?string}> */
    public static function leerCalendario(string $md): array
    {
        $out = [];
        preg_match_all('/(\d{4}-\d{2}-\d{2}) · \*\*(LI-\d+)\*\* (.+?)(?=\s*\|)/', $md, $m, PREG_SET_ORDER);
        foreach ($m as $x) {
            $titulo = trim($x[3]);
            $nota = null;
            if (preg_match('/^(.*?)\s*\(([^()]*)\)$/', $titulo, $p)) {
                $titulo = $p[1];
                $nota = str_starts_with($p[2], 'depende') ? ucfirst($p[2]).'.' : null;
            }
            $out[$x[2]] = ['fecha' => $x[1], 'titulo' => $titulo, 'nota' => $nota];
        }

        return $out;
    }
}
