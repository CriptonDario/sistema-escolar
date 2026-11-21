<?php

namespace App\Http\Controllers\Web;

use App\Enums\EstadoMatricula;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Matricula;
use App\Models\SolicitudMatricula;
use App\Services\Matriculas\MatriculaEnLinea;
use App\Support\Aulas;
use App\Support\RegistroDeSeguridad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MatriculaController extends Controller
{
    public function index(Request $request, MatriculaEnLinea $enLinea): View
    {
        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'seccion_id' => ['nullable', 'integer'],
        ]);
        $anio = (int) ($filtros['anio'] ?? now()->year);

        return view('matriculas.index', [
            'anio' => $anio,
            'filtros' => $filtros,
            'aulas' => Aulas::activas(),
            'estados' => EstadoMatricula::cases(),
            'enLinea' => $enLinea,
            'solicitudes' => SolicitudMatricula::query()
                ->where('estado', SolicitudMatricula::PENDIENTE)
                ->with(['alumno', 'grado', 'apoderado'])
                ->latest()->limit(50)->get(),
            'matriculas' => Matricula::query()
                ->where('anio', $anio)
                ->when($filtros['seccion_id'] ?? null, fn ($q, $id) => $q->where('seccion_id', $id))
                ->with(['alumno', 'seccion.grado'])
                ->join('alumnos', 'alumnos.id', '=', 'matriculas.alumno_id')
                ->orderBy('alumnos.apellidos')->orderBy('alumnos.nombres')
                ->select('matriculas.*')
                ->paginate(30)->withQueryString(),
            // Alumnos activos que todavía no están matriculados ese año.
            'pendientes' => Alumno::query()->activos()
                ->whereDoesntHave('matriculas', fn ($q) => $q->where('anio', $anio))
                ->orderBy('apellidos')->orderBy('nombres')
                ->get(['id', 'codigo', 'nombres', 'apellidos', 'seccion_id']),
        ]);
    }

    /** Abre o cierra la matrícula en línea para los apoderados. */
    public function enLinea(Request $request, MatriculaEnLinea $enLinea): RedirectResponse
    {
        $datos = $request->validate([
            'matricula_anio' => ['required', 'integer', 'between:'.(now()->year).',2100'],
            'matricula_hasta' => ['nullable', 'date_format:Y-m-d'],
        ], ['matricula_anio.between' => 'El año de matrícula no puede ser un año pasado.']);

        $abrir = $request->boolean('matricula_abierta');

        if ($abrir && $enLinea->concepto() === null) {
            return back()->withInput()->withErrors([
                'matricula_abierta' => 'Antes de abrir, crea en "Conceptos de pago" un concepto de tipo Matrícula con su monto.',
            ]);
        }

        $enLinea->configurar($abrir, (int) $datos['matricula_anio'], $datos['matricula_hasta'] ?? null);
        RegistroDeSeguridad::anotar('matricula_en_linea', ($abrir ? 'Abierta' : 'Cerrada')." para {$datos['matricula_anio']}");

        return back()->with('ok', $abrir ? 'Matrícula en línea abierta.' : 'Matrícula en línea cerrada.');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'seccion_id' => ['required', 'integer', Rule::exists('secciones', 'id')->where('activo', true)],
            'anio' => [
                'required', 'integer', 'between:2000,2100',
                Rule::unique('matriculas', 'anio')->where('alumno_id', $request->input('alumno_id')),
            ],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], ['anio.unique' => 'El alumno ya está matriculado en ese año.']);

        DB::transaction(function () use ($datos, $request) {
            Matricula::query()->create($datos + ['registrado_por' => $request->user()->id]);

            // La matrícula del año en curso define el aula actual del alumno.
            if ((int) $datos['anio'] === now()->year) {
                Alumno::query()->whereKey($datos['alumno_id'])->update(['seccion_id' => $datos['seccion_id']]);
            }
        });

        return back()->with('ok', 'Alumno matriculado.');
    }

    public function cambiarEstado(Request $request, Matricula $matricula): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::enum(EstadoMatricula::class)],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $matricula->update($datos);

        return back()->with('ok', 'Matrícula actualizada.');
    }
}
