<?php

use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

// Formulario de contacto de la web → bandeja del CRM. Limitado a 30 envíos por minuto.
Route::post('/contacto', [InboxController::class, 'store'])->middleware('throttle:30,1');
