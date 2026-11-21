<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Personal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Historial de asistencia de alumnos y de personal, con descarga para Excel.
 */
class HistorialController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);

        return view('historial.index', [
            'registros' => $this->consulta($filtros)->paginate(30)->withQueryString(),
            'filtros' => $filtros,
        ]);
    }

    /** Descarga el mismo listado como CSV, que Excel abre directamente. */
    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $nombre = "asistencia_{$filtros['tipo']}_{$filtros['desde']}_a_{$filtros['hasta']}.csv";

        return response()->streamDownload(function () use ($filtros) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // para que Excel lea bien las tildes
            fputcsv($salida, ['Fecha', 'Código', 'Apellidos', 'Nombres', 'Aula o cargo', 'Entrada', 'Salida', 'Estado'], ';');

            $this->consulta($filtros)->chunk(500, function ($registros) use ($salida) {
                foreach ($registros as $registro) {
                    $persona = $registro->persona;

                    if (! ($persona instanceof Alumno || $persona instanceof Personal)) {
                        continue;
                    }

                    fputcsv($salida, [
                        $registro->fecha->format('d/m/Y'),
                        $persona->codigo,
                        $persona->apellidos,
                        $persona->nombres,
                        $persona instanceof Alumno ? $persona->aula() : $persona->cargo,
                        Asistencia::horaCorta($registro->hora_entrada),
                        Asistencia::horaCorta($registro->hora_salida),
                        ucfirst($registro->estado->value),
                    ], ';');
                }
            });

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{tipo: string, desde: string, hasta: string, q: ?string} */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'tipo' => ['nullable', Rule::in(['alumno', 'personal'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        return [
            'tipo' => $datos['tipo'] ?? 'alumno',
            'desde' => $datos['desde'] ?? today()->toDateString(),
            'hasta' => $datos['hasta'] ?? today()->toDateString(),
            'q' => $datos['q'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Asistencia>
     */
    private function consulta(array $filtros): Builder
    {
        $clase = $filtros['tipo'] === 'personal' ? Personal::class : Alumno::class;

        return Asistencia::query()
            ->where('persona_type', $filtros['tipo'])
            ->whereBetween('fecha', [$filtros['desde'], $filtros['hasta']])
            ->when($filtros['q'], fn (Builder $q, string $texto) => $q->whereHasMorph(
                'persona', [$clase], fn (Builder $persona) => $persona->buscar($texto)
            ))
            ->with(['persona' => fn (MorphTo $morph) => $morph->morphWith([Alumno::class => ['seccion.grado']])])
            ->orderByDesc('fecha')->orderByDesc('hora_entrada');
    }
}
