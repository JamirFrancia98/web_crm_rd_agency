<?php

return [
    /*
    | Bandeja de mensajes del formulario. APAGADA por defecto: no recibe datos reales hasta que Jamir la active en el
    | servidor (INBOX_ENABLED=true) tras la revisión legal (base legal, aviso de privacidad, plazo de conservación).
    */
    'enabled' => (bool) env('INBOX_ENABLED', false),

    /*
    | Clave compartida entre el servidor de la web y el CRM (cabecera X-Inbox-Key). La crea y la pone Jamir en el
    | servidor: nunca en el chat, en archivos ni en el repositorio. Sin clave el punto de entrada responde 503.
    */
    'key' => env('INBOX_KEY'),

    /*
    | Días que se conserva un mensaje. NULL = sin plazo definido (PENDIENTE del abogado): no se borra nada solo.
    */
    'retention_days' => env('INBOX_RETENTION_DAYS') !== null ? (int) env('INBOX_RETENTION_DAYS') : null,
];
