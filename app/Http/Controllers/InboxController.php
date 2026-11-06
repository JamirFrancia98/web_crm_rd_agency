<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada del formulario de contacto. Lo llama el SERVIDOR de la web (no el navegador del visitante),
 * porque la clave compartida no puede ir en una página pública. Apagado hasta activarlo en el servidor.
 */
class InboxController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $clave = (string) config('inbox.key');
        if (! config('inbox.enabled') || $clave === '') {
            return response()->json(['ok' => false, 'error' => 'disabled'], 503);
        }
        if (! hash_equals($clave, (string) $request->header('X-Inbox-Key'))) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        // Campo trampa: un bot lo rellena. Se finge éxito y no se guarda nada.
        if (trim((string) $request->input('website', '')) !== '') {
            return response()->json(['ok' => true]);
        }

        $d = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'empresa' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'telefono' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()\-]{6,40}$/'],
            'servicio' => ['nullable', 'string', 'max:80'],
            'presupuesto' => ['nullable', 'string', 'max:80'],
            'mensaje' => ['required', 'string', 'min:10', 'max:5000'],
            'lang' => ['nullable', 'in:es,en,it'],
            'pagina' => ['nullable', 'string', 'max:200'],
            // Sin consentimiento registrado no se guarda nada.
            'consentimiento' => ['accepted'],
            'consentimiento_version' => ['required', 'string', 'max:40'],
        ]);

        $limpio = fn (?string $v) => $v === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
        $dias = config('inbox.retention_days');

        $m = ContactMessage::create([
            'nombre' => $limpio($d['nombre']),
            'empresa' => $limpio($d['empresa'] ?? null) ?: null,
            'email' => mb_strtolower($limpio($d['email'])),
            'telefono' => $limpio($d['telefono'] ?? null) ?: null,
            'servicio' => $limpio($d['servicio'] ?? null) ?: null,
            'presupuesto' => $limpio($d['presupuesto'] ?? null) ?: null,
            'mensaje' => $limpio($d['mensaje']),
            'idioma' => $d['lang'] ?? 'es',
            'pagina' => $limpio($d['pagina'] ?? null) ?: null,
            'consentimiento' => true,
            'consentimiento_version' => $d['consentimiento_version'],
            'consentimiento_en' => now(),
            'conservar_hasta' => $dias ? now()->addDays($dias)->toDateString() : null,
        ]);

        return response()->json(['ok' => true, 'id' => $m->id], 201);
    }
}
