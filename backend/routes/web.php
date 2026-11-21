<?php

use App\Http\Controllers\FotoAlumnoController;
use App\Http\Controllers\FotoPersonalController;
use App\Http\Controllers\MatriculaRetornoController;
use App\Http\Controllers\Web\AjusteController;
use App\Http\Controllers\Web\AlumnoController;
use App\Http\Controllers\Web\ApoderadoController;
use App\Http\Controllers\Web\AsignacionController;
use App\Http\Controllers\Web\CarneController;
use App\Http\Controllers\Web\ComprobanteController;
use App\Http\Controllers\Web\ComunicadoController;
use App\Http\Controllers\Web\ConceptoPagoController;
use App\Http\Controllers\Web\ConductaController;
use App\Http\Controllers\Web\FamiliaController;
use App\Http\Controllers\Web\FamiliaMatriculaController;
use App\Http\Controllers\Web\GastoController;
use App\Http\Controllers\Web\GradoController;
use App\Http\Controllers\Web\HistorialController;
use App\Http\Controllers\Web\HorarioController;
use App\Http\Controllers\Web\IncidenciaController;
use App\Http\Controllers\Web\IntegracionController;
use App\Http\Controllers\Web\InventarioController;
use App\Http\Controllers\Web\KioscoController;
use App\Http\Controllers\Web\MateriaController;
use App\Http\Controllers\Web\MatriculaController;
use App\Http\Controllers\Web\MiCuentaController;
use App\Http\Controllers\Web\NotaController;
use App\Http\Controllers\Web\PagoController;
use App\Http\Controllers\Web\PanelController;
use App\Http\Controllers\Web\PermisoController;
use App\Http\Controllers\Web\PersonalController;
use App\Http\Controllers\Web\SeguridadController;
use App\Http\Controllers\Web\SesionController;
use App\Http\Controllers\Web\TareaController;
use App\Http\Controllers\Web\UsuarioController;
use App\Http\Controllers\Web\VisitaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel web — lo usan el administrador, el guardia y la dirección
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/panel');

Route::middleware('guest')->group(function () {
    Route::get('login', [SesionController::class, 'formulario'])->name('login');
    Route::post('login', [SesionController::class, 'iniciar']);
});

Route::post('logout', [SesionController::class, 'cerrar'])->middleware('auth')->name('logout');

// Adonde la pasarela devuelve al apoderado después de pagar la matrícula.
Route::get('matricula/retorno', MatriculaRetornoController::class)->name('matricula.retorno');

// La foto del alumno: el controlador decide quién puede verla.
Route::get('fotos/alumnos/{alumno}', FotoAlumnoController::class)->middleware('auth')->name('alumnos.foto');
Route::get('fotos/personal/{personal}', FotoPersonalController::class)->middleware('auth')->name('personal.foto');

/*
|--------------------------------------------------------------------------
| Portal de padres y apoderados
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'familia'])->prefix('familia')->name('familia.')->group(function () {
    Route::get('/', [FamiliaController::class, 'inicio'])->name('inicio');
    Route::post('mensajes/leer', [FamiliaController::class, 'leerMensajes'])->name('mensajes.leer');
    Route::get('hijos/{alumno}', [FamiliaController::class, 'hijo'])->name('hijo');

    Route::get('matricula', [FamiliaMatriculaController::class, 'index'])->name('matricula');
    Route::post('matricula/hijos/{alumno}', [FamiliaMatriculaController::class, 'solicitar'])->name('matricula.solicitar');
    Route::post('matricula/solicitudes/{solicitud}/pagar', [FamiliaMatriculaController::class, 'pagar'])->name('matricula.pagar');
});

Route::middleware(['auth', 'panel'])->group(function () {

    Route::get('panel', PanelController::class)->name('panel');
    Route::post('mi-cuenta/clave', [MiCuentaController::class, 'cambiarClave'])->name('mi-cuenta.clave');

    // ── Consulta: admin y dirección ──
    Route::middleware('rol:admin,direccion')->group(function () {
        Route::get('historial', [HistorialController::class, 'index'])->name('historial.index');
        Route::get('historial/exportar', [HistorialController::class, 'exportar'])->name('historial.exportar');
    });

    // ── Puerta: admin y guardia ──
    Route::middleware('rol:admin,guardia')->group(function () {
        Route::get('kiosco', [KioscoController::class, 'index'])->name('kiosco');
        Route::post('kiosco/marcar', [KioscoController::class, 'marcar'])->name('kiosco.marcar');

        Route::get('visitas', [VisitaController::class, 'index'])->name('visitas.index');
        Route::post('visitas', [VisitaController::class, 'store'])->name('visitas.store');
        Route::get('visitas/pases', [VisitaController::class, 'pases'])->name('visitas.pases');
        Route::put('visitas/pases', [VisitaController::class, 'fijarPases'])->name('visitas.pases.fijar');
        Route::post('visitas/pases/devolver', [VisitaController::class, 'devolverPase'])->name('visitas.pases.devolver');
        Route::post('visitas/{visita}/salida', [VisitaController::class, 'salida'])->name('visitas.salida');

        Route::post('incidencias', [IncidenciaController::class, 'store'])->name('incidencias.store');
    });

    // La bitácora la leen los tres perfiles; solo admin y guardia escriben en ella.
    Route::get('incidencias', [IncidenciaController::class, 'index'])->name('incidencias.index');

    Route::middleware('rol:admin')->group(function () {
        // ── Administración y finanzas ──
        Route::get('conceptos', [ConceptoPagoController::class, 'index'])->name('conceptos.index');
        Route::post('conceptos', [ConceptoPagoController::class, 'store'])->name('conceptos.store');
        Route::put('conceptos/{concepto}', [ConceptoPagoController::class, 'update'])->name('conceptos.update');
        Route::post('conceptos/{concepto}/alternar', [ConceptoPagoController::class, 'alternar'])->name('conceptos.alternar');

        Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::post('pagos', [PagoController::class, 'store'])->name('pagos.store');
        Route::get('pagos/{pago}/recibo', [PagoController::class, 'recibo'])->name('pagos.recibo');
        Route::delete('pagos/{pago}', [PagoController::class, 'destroy'])->name('pagos.destroy');
        Route::get('alumnos/{alumno}/estado-de-cuenta', [PagoController::class, 'estadoDeCuenta'])->name('pagos.estado');
        Route::post('pagos/{pago}/comprobante', [ComprobanteController::class, 'store'])->name('pagos.comprobante');
        Route::post('comprobantes/{comprobante}/reenviar', [ComprobanteController::class, 'reenviar'])->name('comprobantes.reenviar');

        Route::get('integraciones', [IntegracionController::class, 'editar'])->name('integraciones.editar');
        Route::put('integraciones', [IntegracionController::class, 'actualizar'])->name('integraciones.actualizar');
        Route::post('integraciones/probar-whatsapp', [IntegracionController::class, 'probarWhatsApp'])->name('integraciones.probar-whatsapp');

        Route::resource('gastos', GastoController::class)->only(['index', 'store', 'destroy']);

        Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
        Route::post('inventario', [InventarioController::class, 'store'])->name('inventario.store');
        Route::post('inventario/{producto}/mover', [InventarioController::class, 'mover'])->name('inventario.mover');
        Route::post('inventario/{producto}/alternar', [InventarioController::class, 'alternar'])->name('inventario.alternar');
    });

    // ── Administración ──
    Route::middleware('rol:admin')->group(function () {
        Route::get('grados', [GradoController::class, 'index'])->name('grados.index');
        Route::post('grados', [GradoController::class, 'store'])->name('grados.store');
        Route::put('grados/{grado}', [GradoController::class, 'update'])->name('grados.update');
        Route::delete('grados/{grado}', [GradoController::class, 'destroy'])->name('grados.destroy');
        Route::post('grados/{grado}/secciones', [GradoController::class, 'agregarSeccion'])->name('grados.secciones.store');
        Route::post('secciones/{seccion}/alternar', [GradoController::class, 'alternarSeccion'])->name('secciones.alternar');
        Route::delete('secciones/{seccion}', [GradoController::class, 'eliminarSeccion'])->name('secciones.destroy');

        Route::get('carnes', [CarneController::class, 'lote'])->name('carnes.lote');
        Route::get('alumnos/{alumno}/carne', [AlumnoController::class, 'carne'])->name('alumnos.carne');
        Route::resource('alumnos', AlumnoController::class)->except('show');
        Route::get('personal/{personal}/carne', [PersonalController::class, 'carne'])->name('personal.carne');
        Route::resource('personal', PersonalController::class)->except('show')->parameters(['personal' => 'personal']);

        Route::get('apoderados', [ApoderadoController::class, 'index'])->name('apoderados.index');
        Route::post('apoderados', [ApoderadoController::class, 'store'])->name('apoderados.store');
        Route::put('apoderados/{apoderado}', [ApoderadoController::class, 'update'])->name('apoderados.update');
        Route::post('apoderados/{apoderado}/alternar', [ApoderadoController::class, 'alternar'])->name('apoderados.alternar');
        Route::post('apoderados/{apoderado}/hijos', [ApoderadoController::class, 'vincular'])->name('apoderados.hijos.store');
        Route::delete('apoderados/{apoderado}/hijos/{alumno}', [ApoderadoController::class, 'desvincular'])->name('apoderados.hijos.destroy');
        Route::post('apoderados/{apoderado}/restablecer-clave', [ApoderadoController::class, 'restablecerClave'])->name('apoderados.restablecer');

        Route::resource('permisos', PermisoController::class)->only(['index', 'store', 'destroy']);

        // ── Académico ──
        Route::get('materias', [MateriaController::class, 'index'])->name('materias.index');
        Route::post('materias', [MateriaController::class, 'store'])->name('materias.store');
        Route::put('materias/{materia}', [MateriaController::class, 'update'])->name('materias.update');
        Route::post('materias/{materia}/alternar', [MateriaController::class, 'alternar'])->name('materias.alternar');

        Route::get('matriculas', [MatriculaController::class, 'index'])->name('matriculas.index');
        Route::post('matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
        Route::put('matriculas/en-linea', [MatriculaController::class, 'enLinea'])->name('matriculas.en-linea');
        Route::put('matriculas/{matricula}', [MatriculaController::class, 'cambiarEstado'])->name('matriculas.estado');

        Route::resource('asignaciones', AsignacionController::class)->only(['index', 'store', 'destroy'])->parameters(['asignaciones' => 'asignacion']);

        Route::get('notas', [NotaController::class, 'index'])->name('notas.index');
        Route::post('notas', [NotaController::class, 'guardar'])->name('notas.guardar');

        Route::get('horarios', [HorarioController::class, 'index'])->name('horarios.index');
        Route::post('horarios', [HorarioController::class, 'store'])->name('horarios.store');
        Route::delete('horarios/{horario}', [HorarioController::class, 'destroy'])->name('horarios.destroy');

        Route::resource('tareas', TareaController::class)->only(['index', 'store', 'destroy']);
        Route::resource('conducta', ConductaController::class)->only(['index', 'store', 'destroy'])->parameters(['conducta' => 'conducta']);
        Route::resource('comunicados', ComunicadoController::class)->only(['index', 'store']);

        Route::get('seguridad', [SeguridadController::class, 'index'])->name('seguridad.index');
        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::post('usuarios/{usuario}/alternar', [UsuarioController::class, 'alternar'])->name('usuarios.alternar');

        Route::get('configuracion', [AjusteController::class, 'editar'])->name('ajustes.editar');
        Route::put('configuracion', [AjusteController::class, 'actualizar'])->name('ajustes.actualizar');
    });
});
