<?php

namespace App\Http\Controllers\Web;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Visita;
use App\Services\Visitas\PasesDeVisita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Registro de visitantes en la puerta, con sus pases genéricos.
 */
class VisitaController extends Controller
{
    public function index(Request $request, PasesDeVisita $pases): View
    {
        $filtros = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);
        $fecha = $filtros['fecha'] ?? today()->toDateString();

        return view('visitas.index', [
            'fecha' => $fecha,
            'visitas' => Visita::query()->whereDate('entrada', $fecha)->orderByDesc('entrada')->get(),
            'adentro' => Visita::query()->whereNull('salida')->count(),
            'pasesLibres' => $pases->libres(),
            'totalDePases' => $pases->cantidad(),
        ]);
    }

    public function store(Request $request, PasesDeVisita $pases): RedirectResponse
    {
        $request->merge(['pase' => PasesDeVisita::normalizar((string) $request->input('pase')) ?: null]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'documento' => ['nullable', 'string', 'max:20'],
            'motivo' => ['required', 'string', 'max:255'],
            'visita_a' => ['nullable', 'string', 'max:120'],
            // Solo un pase que exista y que nadie tenga en este momento.
            'pase' => ['nullable', Rule::in($pases->libres())],
        ], ['pase.in' => 'Ese pase no existe o lo tiene otro visitante que todavía no sale.']);

        Visita::query()->create($datos + ['entrada' => now(), 'registrado_por' => $request->user()->id]);

        return back()->with('ok', "Ingreso de {$datos['nombre']} registrado.".(empty($datos['pase']) ? '' : " Entrégale el pase {$datos['pase']}."));
    }

    public function salida(Visita $visita): RedirectResponse
    {
        Visita::query()->whereKey($visita->id)->whereNull('salida')->update(['salida' => now()]);

        return back()->with('ok', "Salida de {$visita->nombre} registrada.".($visita->pase ? " Recoge el pase {$visita->pase}." : ''));
    }

    /** El visitante entrega su pase al salir: con el código basta para marcar la salida. */
    public function devolverPase(Request $request, PasesDeVisita $pases): RedirectResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:10']]);
        $visita = $pases->devolver($datos['codigo']);

        return $visita === null
            ? back()->with('error', 'El pase '.PasesDeVisita::normalizar($datos['codigo']).' no está entregado a ningún visitante.')
            : back()->with('ok', "Salida de {$visita->nombre} registrada. Pase {$visita->pase} devuelto.");
    }

    /** Los pases, listos para imprimir a tamaño DNI. */
    public function pases(PasesDeVisita $pases): View
    {
        return view('visitas.pases', ['codigos' => $pases->codigos()]);
    }

    /** Cuántos pases tiene el colegio. Lo decide el administrador. */
    public function fijarPases(Request $request, PasesDeVisita $pases): RedirectResponse
    {
        abort_unless($request->user()->tieneRol(Rol::Admin), 403);

        $datos = $request->validate(['cantidad' => ['required', 'integer', 'between:1,'.PasesDeVisita::MAXIMO]]);
        $pases->fijarCantidad((int) $datos['cantidad']);

        return back()->with('ok', "El colegio tiene ahora {$datos['cantidad']} pase(s) de visitante. Imprime los que falten.");
    }
}
