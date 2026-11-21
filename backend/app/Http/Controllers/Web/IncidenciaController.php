<?php

namespace App\Http\Controllers\Web;

use App\Enums\Severidad;
use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bitácora: lo que el guardia o la administración dejan anotado.
 */
class IncidenciaController extends Controller
{
    public function index(): View
    {
        return view('incidencias.index', [
            'incidencias' => Incidencia::query()->with('autor')->latest()->paginate(20),
            'severidades' => Severidad::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['required', 'string', 'max:3000'],
            'severidad' => ['required', Rule::enum(Severidad::class)],
        ]);

        Incidencia::query()->create($datos + ['registrado_por' => $request->user()->id]);

        return back()->with('ok', 'Incidencia registrada.');
    }
}
