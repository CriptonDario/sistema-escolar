<?php

namespace App\Http\Controllers\Web;

use App\Actions\PublicarTarea;
use App\Enums\TipoTarea;
use App\Http\Controllers\Controller;
use App\Models\Materia;
use App\Models\Tarea;
use App\Support\Aulas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tareas, exámenes y actividades por aula. Publicar una le avisa a los
 * apoderados de esa aula en la app.
 */
class TareaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate(['seccion_id' => ['nullable', 'integer']]);

        return view('tareas.index', [
            'filtros' => $filtros,
            'aulas' => Aulas::activas(),
            'tipos' => TipoTarea::cases(),
            'materias' => Materia::query()->where('activo', true)->orderBy('nombre')->get(),
            'tareas' => Tarea::query()
                ->with(['seccion.grado', 'materia'])
                ->when($filtros['seccion_id'] ?? null, fn ($q, $id) => $q->where('seccion_id', $id))
                ->orderByDesc('fecha_entrega')->orderByDesc('id')
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request, PublicarTarea $publicar): RedirectResponse
    {
        $datos = $request->validate([
            'seccion_id' => ['required', 'integer', Rule::exists('secciones', 'id')],
            'materia_id' => ['nullable', 'integer', Rule::exists('materias', 'id')],
            'tipo' => ['required', Rule::enum(TipoTarea::class)],
            'titulo' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_entrega' => ['required', 'date_format:Y-m-d'],
        ]);

        $publicar->ejecutar($datos, $request->user());

        return back()->with('ok', 'Publicado. Se avisó a los apoderados del aula.');
    }

    /** Anula la tarea: deja de verse en la app. */
    public function destroy(Tarea $tarea): RedirectResponse
    {
        $tarea->update(['anulado_en' => now()]);

        return back()->with('ok', 'Tarea anulada.');
    }
}
