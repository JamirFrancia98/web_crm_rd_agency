<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Imagen de portada de un artículo: una sola, sirve de banner en la web y de miniatura al compartir en redes.
 * Medidas recomendadas: 1920×1080 px (16:9), WebP, menos de 200 KB. Las redes recortan a 1200×630, así que
 * lo importante va en el centro.
 */
final class Portada
{
    public const ANCHO = 1920;

    public const ALTO = 1080;

    public const MAX_KB_AVISO = 200;

    public const MAX_KB_DURO = 1024;

    public const RUTA_WEB = '/img/blog/';

    /** URL para mostrar la imagen en el CRM, desde el estado de un FileUpload, una ruta guardada o la ruta web. */
    public static function url(mixed $estado, ?string $rutaWeb = null): ?string
    {
        if (is_array($estado)) {
            $estado = reset($estado) ?: null;
        }
        if ($estado instanceof TemporaryUploadedFile) {
            try {
                return $estado->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }
        if (is_string($estado) && $estado !== '') {
            return Storage::disk('public')->url($estado);
        }
        if ($rutaWeb) {
            return str_starts_with($rutaWeb, 'http') ? $rutaWeb : 'https://revolutiondigital.agency/'.ltrim($rutaWeb, '/');
        }

        return null;
    }

    /** @return array{ancho: int, alto: int, bytes: int}|null */
    public static function medir(string $rutaEnDisco): ?array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($rutaEnDisco)) {
            return null;
        }
        $info = @getimagesizefromstring((string) $disk->get($rutaEnDisco));
        if ($info === false) {
            return null;
        }

        return ['ancho' => (int) $info[0], 'alto' => (int) $info[1], 'bytes' => (int) $disk->size($rutaEnDisco)];
    }

    /** Avisos (no bloquean) sobre medidas, formato y peso. */
    public static function avisos(?int $ancho, ?int $alto, ?int $bytes, ?string $archivo): array
    {
        if (! $ancho || ! $alto) {
            return [];
        }
        $w = [];
        if (abs($ancho / $alto - 16 / 9) > 0.02) {
            $w[] = "La imagen mide {$ancho}×{$alto} px: no es 16:9. Se recortará en el banner o en redes; recomendado ".self::ANCHO.'×'.self::ALTO.' px.';
        }
        if ($ancho < 1200) {
            $w[] = "La imagen es estrecha ({$ancho} px): las redes piden al menos 1200 px de ancho.";
        }
        if ($bytes && $bytes > self::MAX_KB_AVISO * 1024) {
            $w[] = 'La imagen pesa '.self::kb($bytes).': conviene menos de '.self::MAX_KB_AVISO.' KB para que la web cargue rápido.';
        }
        if ($archivo && ! str_ends_with(strtolower($archivo), '.webp')) {
            $w[] = 'La web espera WebP; esta imagen no lo es. Funciona, pero pesa más.';
        }

        return $w;
    }

    public static function kb(int $bytes): string
    {
        return number_format($bytes / 1024, 0, ',', '.').' KB';
    }

    /** Resumen legible: «1920×1080 px · 16:9 · 143 KB · WebP». */
    public static function resumen(?int $ancho, ?int $alto, ?int $bytes, ?string $archivo): string
    {
        if (! $ancho || ! $alto) {
            return 'Sin imagen. Medidas recomendadas: '.self::ANCHO.'×'.self::ALTO.' px (16:9), WebP, menos de '.self::MAX_KB_AVISO.' KB.';
        }
        $ratio = abs($ancho / $alto - 16 / 9) <= 0.02 ? '16:9' : 'proporción '.round($ancho / $alto, 2).':1';

        return "{$ancho}×{$alto} px · {$ratio} · ".($bytes ? self::kb($bytes) : '—').' · '.strtoupper(pathinfo((string) $archivo, PATHINFO_EXTENSION));
    }
}
