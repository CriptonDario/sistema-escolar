<?php

/*
|--------------------------------------------------------------------------
| Configuración propia del sistema escolar
|--------------------------------------------------------------------------
|
| Valores de arranque. Los de "asistencia" son solo el punto de partida: la
| dirección puede cambiarlos desde el sistema (tabla `ajustes`) y esos mandan.
|
*/

return [

    // Carpeta "uploads" del sistema anterior: de ahí el importador copia las fotos.
    'anterior_fotos' => env('ANTERIOR_RUTA_FOTOS'),

    // Contraseña con la que nace toda cuenta de alumno, personal o apoderado.
    // Quien entra con ella está obligado a cambiarla antes de usar el sistema.
    'clave_inicial' => env('COLEGIO_CLAVE_INICIAL', '123456'),

    // Días que dura una sesión de la app antes de pedir login de nuevo.
    'dias_sesion_app' => (int) env('COLEGIO_DIAS_SESION_APP', 90),

    'asistencia' => [
        // Hasta qué hora (inclusive, minuto completo) el ingreso es puntual.
        'hora_entrada' => '07:30',
        // Minutos de gracia después de la hora de entrada.
        'tolerancia_minutos' => 0,
        // Un segundo escaneo antes de este tiempo NO es salida: evita que un
        // doble escaneo en la puerta le avise al apoderado que su hijo "salió"
        // un minuto después de entrar.
        'minutos_minimos_para_salida' => 60,
    ],

    'push' => [
        // Ruta al JSON de la cuenta de servicio de Firebase. Vacío = sin push:
        // los mensajes igual quedan en la bandeja de la app.
        'credenciales_fcm' => env('FCM_CREDENCIALES'),
    ],

];
