@php($logs = $getRecord()?->logs ?? collect())

@if ($logs->isEmpty())
    <p style="opacity: .6;">Sin movimientos.</p>
@else
    <ul style="list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; font-size: 14px;">
        @foreach ($logs as $l)
            <li>{{ $l->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} — {{ $l->accion }} — {{ $l->usuario }}@if ($l->nota) — «{{ $l->nota }}»@endif</li>
        @endforeach
    </ul>
@endif
