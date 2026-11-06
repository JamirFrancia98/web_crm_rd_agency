<?php

return [
    /*
    | Verificación en dos pasos obligatoria en el panel. Debe estar activa siempre (también en producción).
    | Solo se desactiva (CRM_MFA=false) en una copia local desechable para revisar pantallas con una base de datos de prueba.
    */
    'mfa' => (bool) env('CRM_MFA', true),
];
