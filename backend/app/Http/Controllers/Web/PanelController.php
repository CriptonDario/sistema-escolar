<?php

namespace App\Http\Controllers\Web;

use App\Enums\EstadoAsistencia;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Models\Seccion;
use App\Support\Ajustes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\View\View;

class PanelController extends Controller
{
    /** Pantalla de inicio: cómo va el día. */
    public function __invoke(Ajustes $ajustes): View
    {
        return view('panel.inicio', [
            'horaEntrada' => $ajustes->horaEntrada(),
            'alumnos' => $this->resumen('alumno', Alumno::query()->activos()->count()),
            'personal' => $this->resumen('personal', Personal::query()->activos()->count()),
            'secciones' => Seccion::query()->where('activo', true)->count(),
            'apoderados' => Apoderado::query()->count(),
            'ultimas' => Asistencia::query()
                ->whereDate('fecha', today())
                ->with(['persona' => fn (MorphTo $morph) => $morph->morphWith([Alumno::class => ['seccion.grado']])])
                ->latest('updated_at')->limit(15)->get(),
        ]);
    }

    /** @return array{total: int, presentes: int, tardanzas: int, salieron: int} */
    private function resumen(string $tipoPersona, int $total): array
    {
        $delDia = Asistencia::query()->whereDate('fecha', today())->where('persona_type', $tipoPersona);

        return [
            'total' => $total,
            'presentes' => (clone $delDia)->count(),
            'tardanzas' => (clone $delDia)->where('estado', EstadoAsistencia::Tarde)->count(),
            'salieron' => (clone $delDia)->whereNotNull('hora_salida')->count(),
        ];
    }
}
