<?php

namespace App\Http\Controllers\Web;

use App\Actions\GuardarAlumno;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarAlumnoRequest;
use App\Models\Alumno;
use App\Services\Cuentas\CreadorDeCuentas;
use App\Support\Aulas;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlumnoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'seccion_id' => ['nullable', 'integer'],
        ]);

        $alumnos = Alumno::query()
            ->with(['seccion.grado', 'user'])
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->buscar($texto))
            ->when($filtros['seccion_id'] ?? null, fn ($q, $id) => $q->where('seccion_id', $id))
            ->orderByDesc('activo')->orderBy('apellidos')->orderBy('nombres')
            ->paginate(25)->withQueryString();

        return view('alumnos.index', ['alumnos' => $alumnos, 'aulas' => $this->aulas(), 'filtros' => $filtros]);
    }

    /** El carné del alumno, listo para imprimir a tamaño DNI. */
    public function carne(Alumno $alumno): View
    {
        return view('alumnos.carne', ['alumno' => $alumno->load(['seccion.grado', 'apoderados'])]);
    }

    public function create(): View
    {
        return view('alumnos.form', ['alumno' => new Alumno, 'aulas' => $this->aulas()]);
    }

    public function store(GuardarAlumnoRequest $request, GuardarAlumno $guardar): RedirectResponse
    {
        $alumno = $guardar->ejecutar($request->validated());

        return redirect()->route('alumnos.index')->with('ok', "{$alumno->nombreCompleto()} registrado.");
    }

    public function edit(Alumno $alumno): View
    {
        return view('alumnos.form', ['alumno' => $alumno->load('user'), 'aulas' => $this->aulas()]);
    }

    public function update(GuardarAlumnoRequest $request, Alumno $alumno, GuardarAlumno $guardar): RedirectResponse
    {
        $guardar->ejecutar($request->validated() + ['activo' => $request->boolean('activo')], $alumno);

        return redirect()->route('alumnos.index')->with('ok', 'Datos actualizados.');
    }

    public function destroy(Alumno $alumno, CreadorDeCuentas $cuentas): RedirectResponse
    {
        $cuentas->darDeBaja($alumno);

        return back()->with('ok', "{$alumno->nombreCompleto()} fue dado de baja.");
    }

    private function aulas(): Collection
    {
        return Aulas::activas();
    }
}
