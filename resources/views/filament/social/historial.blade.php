@php
    $registro = $getRecord();
    $logs = $registro ? $registro->logs : collect();
@endphp

@if ($logs->isEmpty())
    <p style="opacity: .6;">Sin movimientos todavía.</p>
@else
    <ul style="list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; font-size: 14px;">
        @foreach ($logs as $l)
            <li>
                {{ $l->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} —
                {{ \App\Models\SocialPost::ESTADOS[$l->estado_anterior] ?? ($l->estado_anterior ?: 'inicio') }} →
                <strong>{{ \App\Models\SocialPost::ESTADOS[$l->estado_nuevo] ?? $l->estado_nuevo }}</strong> —
                {{ $l->usuario }}@if ($l->nota) — «{{ $l->nota }}»@endif
            </li>
        @endforeach
    </ul>
@endif
