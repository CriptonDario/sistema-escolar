<?php

use App\Http\Controllers\Api\V1\Admin\AlumnoController;
use App\Http\Controllers\Api\V1\Admin\ApoderadoController;
use App\Http\Controllers\Api\V1\Admin\GradoController;
use App\Http\Controllers\Api\V1\Admin\PermisoController;
use App\Http\Controllers\Api\V1\Admin\PersonalController;
use App\Http\Controllers\Api\V1\Admin\SeccionController;
use App\Http\Controllers\Api\V1\Apoderado\HijoController;
use App\Http\Controllers\Api\V1\Apoderado\MatriculaController;
use App\Http\Controllers\Api\V1\Apoderado\MensajeController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DispositivoController;
use App\Http\Controllers\Api\V1\DocenteController;
use App\Http\Controllers\Api\V1\ExpedienteController;
use App\Http\Controllers\Api\V1\MiAsistenciaController;
use App\Http\Controllers\Api\V1\PagoEnLineaController;
use App\Http\Controllers\FotoAlumnoController;
use App\Http\Controllers\FotoPersonalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — la consume la app móvil
|--------------------------------------------------------------------------
| Todas las rutas quedan bajo /api/v1. Salvo el login, exigen el token en la
| cabecera "Authorization: Bearer <token>".
*/

// El prefijo de nombre "api." es imprescindible: el panel web tiene rutas con
// los mismos nombres (alumnos.index, personal.store...). Sin él, route() en
// las vistas del panel resolvía hacia la API y los enlaces y formularios del
// panel terminaban aquí.
Route::prefix('v1')->name('api.')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);

    // Avisos de la pasarela de pagos. Sin sesión: el pago se verifica consultándolo a la pasarela.
    Route::post('pagos/mercadopago', [PagoEnLineaController::class, 'mercadoPago'])->middleware('throttle:120,1')->name('pagos.mercadopago');

    Route::middleware('auth:sanctum')->group(function () {

        // Disponibles aun con la contraseña inicial sin cambiar.
        Route::get('perfil', [AuthController::class, 'perfil']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/clave', [AuthController::class, 'cambiarClave']);
        Route::get('alumnos/{alumno}/foto', FotoAlumnoController::class)->name('alumnos.foto');
        Route::get('personal/{personal}/foto', FotoPersonalController::class)->name('personal.foto');

        Route::middleware('cuenta.habilitada')->group(function () {

            Route::post('dispositivos', [DispositivoController::class, 'store']);

            // ── Admin y guardia: registrar asistencia ──
            Route::middleware('rol:admin,guardia')->prefix('asistencia')->group(function () {
                Route::post('marcar', [AsistenciaController::class, 'marcar']);
                Route::get('hoy', [AsistenciaController::class, 'hoy']);
                Route::get('personas', [AsistenciaController::class, 'personas']);
            });

            // ── Apoderado ──
            Route::middleware('rol:apoderado')->group(function () {
                Route::get('hijos', [HijoController::class, 'index']);
                Route::get('hijos/{alumno}/asistencia', [HijoController::class, 'asistencia']);
                Route::get('hijos/{alumno}/notas', [ExpedienteController::class, 'notas']);
                Route::get('hijos/{alumno}/horario', [ExpedienteController::class, 'horario']);
                Route::get('hijos/{alumno}/tareas', [ExpedienteController::class, 'tareas']);
                Route::get('hijos/{alumno}/conducta', [ExpedienteController::class, 'conducta']);
                Route::get('hijos/{alumno}/comunicados', [ExpedienteController::class, 'comunicados']);
                Route::get('hijos/{alumno}/pagos', [ExpedienteController::class, 'pagos']);
                Route::get('matricula', [MatriculaController::class, 'index']);
                Route::post('matricula/hijos/{alumno}', [MatriculaController::class, 'solicitar']);
                Route::post('matricula/solicitudes/{solicitud}/pagar', [MatriculaController::class, 'pagar']);
                Route::get('mensajes', [MensajeController::class, 'index']);
                Route::post('mensajes/leer-todos', [MensajeController::class, 'leerTodos']);
                Route::post('mensajes/{mensaje}/leer', [MensajeController::class, 'leer']);
            });

            // ── Alumno: su propio expediente ──
            Route::middleware('rol:alumno')->prefix('mi')->group(function () {
                Route::get('notas', [ExpedienteController::class, 'notas']);
                Route::get('horario', [ExpedienteController::class, 'horario']);
                Route::get('tareas', [ExpedienteController::class, 'tareas']);
                Route::get('conducta', [ExpedienteController::class, 'conducta']);
                Route::get('comunicados', [ExpedienteController::class, 'comunicados']);
            });

            // ── Docente: sus aulas, notas, tareas y conducta ──
            Route::middleware('rol:personal')->prefix('docente')->group(function () {
                Route::get('aulas', [DocenteController::class, 'aulas']);
                Route::get('horario', [DocenteController::class, 'horario']);
                Route::get('aulas/{seccion}/notas', [DocenteController::class, 'notas']);
                Route::post('aulas/{seccion}/notas', [DocenteController::class, 'guardarNotas']);
                Route::post('aulas/{seccion}/tareas', [DocenteController::class, 'publicarTarea']);
                Route::post('conducta', [DocenteController::class, 'registrarConducta']);
            });

            // ── Alumno y personal ──
            Route::middleware('rol:alumno,personal')->get('mi/asistencia', MiAsistenciaController::class);

            // ── Administración ──
            Route::middleware('rol:admin')->prefix('admin')->group(function () {
                Route::apiResource('grados', GradoController::class)->except('show');
                Route::post('grados/{grado}/secciones', [SeccionController::class, 'store']);
                Route::match(['put', 'patch'], 'secciones/{seccion}', [SeccionController::class, 'update']);
                Route::delete('secciones/{seccion}', [SeccionController::class, 'destroy']);

                Route::apiResource('alumnos', AlumnoController::class);
                Route::apiResource('personal', PersonalController::class)->parameters(['personal' => 'personal']);

                Route::apiResource('apoderados', ApoderadoController::class);
                Route::post('apoderados/{apoderado}/hijos', [ApoderadoController::class, 'vincular']);
                Route::delete('apoderados/{apoderado}/hijos/{alumno}', [ApoderadoController::class, 'desvincular']);
                Route::post('apoderados/{apoderado}/restablecer-clave', [ApoderadoController::class, 'restablecerClave']);

                Route::apiResource('permisos', PermisoController::class)->only(['index', 'store', 'destroy']);
            });
        });
    });
});
