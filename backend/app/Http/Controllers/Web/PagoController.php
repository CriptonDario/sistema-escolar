<?php

namespace App\Http\Controllers\Web;

use App\Enums\MetodoPago;
use App\Enums\TipoConcepto;
use App\Enums\TipoMensaje;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\ConceptoPago;
use App\Models\Pago;
use App\Services\Matriculas\MatriculaEnLinea;
use App\Services\Mensajeria\Mensajero;
use App\Services\Pagos\EstadoDeCuenta;
use App\Services\Sunat\Nubefact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PagoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $filtros['hasta'] ?? now()->endOfMonth()->toDateString();

        $enRango = Pago::query()->whereBetween('fecha', [$desde, $hasta])
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereHas('alumno', fn ($a) => $a->buscar($texto)));

        return view('pagos.index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'filtros' => $filtros,
            'total' => (clone $enRango)->vigentes()->sum('monto'),
            'pagos' => $enRango->with(['alumno', 'concepto'])->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString(),
            'alumnos' => Alumno::query()->activos()->orderBy('apellidos')->orderBy('nombres')->get(['id', 'codigo', 'nombres', 'apellidos']),
            'conceptos' => ConceptoPago::query()->where('activo', true)->orderBy('nombre')->get(),
            'metodos' => MetodoPago::deCaja(),
            'meses' => Pago::MESES,
        ]);
    }

    public function store(Request $request, Mensajero $mensajero): RedirectResponse
    {
        $datos = $request->validate([
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'concepto_id' => ['required', 'integer', Rule::exists('conceptos_pago', 'id')->where('activo', true)],
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'metodo' => ['required', Rule::enum(MetodoPago::class)],
            'numero_operacion' => ['nullable', 'string', 'max:50'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $concepto = ConceptoPago::query()->findOrFail($datos['concepto_id']);

        if ($concepto->tipo === TipoConcepto::Pension) {
            if (empty($datos['mes'])) {
                return back()->withInput()->withErrors(['mes' => 'Indica el mes de la pensión.']);
            }

            // La misma pensión no se cobra dos veces.
            $yaPagada = Pago::query()->vigentes()->where([
                'alumno_id' => $datos['alumno_id'], 'concepto_id' => $concepto->id,
                'anio' => $datos['anio'], 'mes' => $datos['mes'],
            ])->exists();

            if ($yaPagada) {
                return back()->withInput()->withErrors(['mes' => 'Esa pensión ya está pagada.']);
            }
        } else {
            $datos['mes'] = null;
        }

        $pago = Pago::query()->create($datos + ['registrado_por' => $request->user()->id]);
        $pago->load(['alumno', 'concepto']);

        $mensajero->avisarApoderadosDe(
            $pago->alumno,
            TipoMensaje::Pago,
            'Pago registrado',
            sprintf('Se registró el pago de %s de %s por S/ %s. Recibo N.° %s.',
                $pago->detalle(), $pago->alumno->nombreCompleto(), number_format($pago->monto, 2), $pago->numeroRecibo()),
        );

        // Si era la matrícula que el apoderado pidió en línea, queda confirmada.
        app(MatriculaEnLinea::class)->alRegistrarPago($pago);

        return redirect()->route('pagos.recibo', $pago)->with('ok', 'Pago registrado.');
    }

    public function recibo(Pago $pago, Nubefact $nubefact): View
    {
        return view('pagos.recibo', [
            'pago' => $pago->load(['alumno.seccion.grado', 'alumno.apoderados', 'concepto', 'comprobantes']),
            'facturacionActiva' => $nubefact->disponible(),
        ]);
    }

    /** Anula el pago (no se borra): deja de contar en los totales y en el estado de cuenta. */
    public function destroy(Pago $pago): RedirectResponse
    {
        Pago::query()->whereKey($pago->id)->whereNull('anulado_en')->update(['anulado_en' => now()]);

        return back()->with('ok', 'Pago anulado.');
    }

    public function estadoDeCuenta(Request $request, Alumno $alumno, EstadoDeCuenta $estado): View
    {
        $anio = (int) $request->query('anio', now()->year);

        return view('pagos.estado', [
            'alumno' => $alumno->load('seccion.grado'),
            'estado' => $estado->de($alumno, ($anio >= 2000 && $anio <= 2100) ? $anio : now()->year),
        ]);
    }
}
