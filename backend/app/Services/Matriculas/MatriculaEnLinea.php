<?php

namespace App\Services\Matriculas;

use App\Enums\MetodoPago;
use App\Enums\TipoConcepto;
use App\Enums\TipoMensaje;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\ConceptoPago;
use App\Models\Grado;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\Seccion;
use App\Models\SolicitudMatricula;
use App\Services\Mensajeria\Mensajero;
use App\Services\Pagos\MercadoPago;
use App\Support\Ajustes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Matrícula en línea.
 *
 * El administrador la abre para un año. Mientras esté abierta, cada apoderado
 * puede pedir la matrícula de sus hijos. La matrícula solo se hace efectiva
 * con el pago, que puede llegar por dos caminos:
 *
 *   - en caja: secretaría registra el pago de matrícula como siempre;
 *   - en línea: el apoderado paga en la pasarela y esta lo confirma al instante.
 *
 * En cuanto el pago existe, el alumno queda matriculado y se avisa a la familia.
 */
class MatriculaEnLinea
{
    public function __construct(
        private readonly Ajustes $ajustes,
        private readonly Mensajero $mensajero,
        private readonly MercadoPago $pasarela,
    ) {}

    // ── Configuración ────────────────────────────────────────────────────────

    public function activada(): bool
    {
        return $this->ajustes->obtener('matricula_abierta', '0') === '1';
    }

    public function anio(): int
    {
        return (int) $this->ajustes->obtener('matricula_anio', now()->year);
    }

    public function fechaLimite(): ?Carbon
    {
        $fecha = (string) $this->ajustes->obtener('matricula_hasta', '');

        return $fecha === '' ? null : Carbon::parse($fecha);
    }

    /** ¿Pueden los apoderados pedir matrícula hoy? */
    public function abierta(): bool
    {
        $limite = $this->fechaLimite();

        return $this->activada()
            && ($limite === null || today()->lessThanOrEqualTo($limite))
            && $this->concepto() !== null;
    }

    /** El concepto de pago que se cobra por la matrícula. */
    public function concepto(): ?ConceptoPago
    {
        return ConceptoPago::query()->where('tipo', TipoConcepto::Matricula->value)->where('activo', true)->orderBy('id')->first();
    }

    public function monto(): float
    {
        return (float) ($this->concepto()->monto ?? 0);
    }

    public function pagoEnLineaDisponible(): bool
    {
        return $this->pasarela->disponible();
    }

    public function configurar(bool $abierta, int $anio, ?string $hasta): void
    {
        $this->ajustes->guardar('matricula_abierta', $abierta ? '1' : '0');
        $this->ajustes->guardar('matricula_anio', (string) $anio);
        $this->ajustes->guardar('matricula_hasta', (string) $hasta);
    }

    /**
     * Grados a los que se puede matricular: activos y con alguna sección activa.
     *
     * @return Collection<int, Grado>
     */
    public function gradosDisponibles(): Collection
    {
        return Grado::query()->where('activo', true)
            ->whereHas('secciones', fn ($q) => $q->where('activo', true))
            ->ordenados()->get();
    }

    // ── Lo que ve el apoderado ───────────────────────────────────────────────

    /**
     * El estado de la matrícula de cada hijo. Lo usan el portal y la app.
     *
     * @return array<string, mixed>
     */
    public function resumenPara(Apoderado $apoderado): array
    {
        $anio = $this->anio();
        $grados = $this->gradosDisponibles()->values();
        $hijos = $apoderado->hijos()->activos()->with('seccion.grado')->orderBy('apellidos')->orderBy('nombres')->get();

        $matriculas = Matricula::query()->where('anio', $anio)->whereIn('alumno_id', $hijos->modelKeys())
            ->with('seccion.grado')->get()->keyBy('alumno_id');
        $solicitudes = SolicitudMatricula::query()->where('anio', $anio)->whereIn('alumno_id', $hijos->modelKeys())
            ->with('grado')->get()->keyBy('alumno_id');

        return [
            'abierta' => $this->abierta(),
            'anio' => $anio,
            'monto' => $this->monto(),
            'fecha_limite' => $this->fechaLimite()?->toDateString(),
            'pago_en_linea' => $this->pagoEnLineaDisponible(),
            'grados' => $grados->map(fn (Grado $grado) => ['id' => $grado->id, 'nombre' => $grado->nombre])->all(),
            'hijos' => $hijos->map(function (Alumno $hijo) use ($matriculas, $solicitudes, $grados, $anio) {
                $matricula = $matriculas->get($hijo->id);
                $solicitud = $solicitudes->get($hijo->id);

                return [
                    'id' => $hijo->id,
                    'nombre' => $hijo->nombreCompleto(),
                    'aula_actual' => $hijo->aula(),
                    'estado' => match (true) {
                        $matricula !== null => 'matriculado',
                        $solicitud !== null => 'pendiente_pago',
                        default => 'sin_solicitar',
                    },
                    'aula' => $matricula?->seccion->nombreCompleto(),
                    'solicitud_id' => $matricula === null ? $solicitud?->id : null,
                    'grado_solicitado' => $solicitud?->grado->nombre,
                    'monto' => $solicitud?->monto,
                    'grado_sugerido_id' => $this->gradoSugerido($hijo, $grados, $anio),
                ];
            })->all(),
        ];
    }

    /**
     * El mismo grado si es para este año; el siguiente si es para el próximo.
     *
     * @param  Collection<int, Grado>  $grados
     */
    private function gradoSugerido(Alumno $alumno, Collection $grados, int $anio): ?int
    {
        $actual = $alumno->seccion?->grado_id;
        $posicion = $grados->search(fn (Grado $grado) => $grado->id === $actual);

        if ($actual === null || $posicion === false) {
            return null;
        }

        return $anio <= now()->year ? $actual : $grados->get($posicion + 1)?->id;
    }

    // ── Solicitar ────────────────────────────────────────────────────────────

    /**
     * El apoderado pide la matrícula de su hijo. Si el pago ya estaba
     * registrado en caja, queda matriculado en el acto.
     */
    public function solicitar(Apoderado $apoderado, Alumno $alumno, int $gradoId): SolicitudMatricula
    {
        if (! $this->abierta()) {
            $this->rechazar('La matrícula en línea no está abierta en este momento.');
        }
        if (! $this->gradosDisponibles()->contains('id', $gradoId)) {
            $this->rechazar('Ese grado no está disponible para matrícula.');
        }

        $anio = $this->anio();

        if (Matricula::query()->where(['alumno_id' => $alumno->id, 'anio' => $anio])->exists()) {
            $this->rechazar("{$alumno->nombreCompleto()} ya está matriculado en {$anio}.");
        }

        $solicitud = SolicitudMatricula::query()->firstOrNew(['alumno_id' => $alumno->id, 'anio' => $anio]);
        $solicitud->fill([
            'apoderado_id' => $apoderado->id,
            'grado_id' => $gradoId,
            'monto' => $this->monto(),
            'estado' => SolicitudMatricula::PENDIENTE,
            'referencia' => $solicitud->referencia ?? (string) Str::uuid(),
        ])->save();

        if ($pago = $this->pagoDeMatricula($alumno->id, $anio)) {
            $this->confirmar($solicitud, $pago);
        }

        return $solicitud->refresh();
    }

    private function pagoDeMatricula(int $alumnoId, int $anio): ?Pago
    {
        return Pago::query()->vigentes()
            ->where(['alumno_id' => $alumnoId, 'anio' => $anio])
            ->whereHas('concepto', fn ($q) => $q->where('tipo', TipoConcepto::Matricula->value))
            ->first();
    }

    // ── Pagar ────────────────────────────────────────────────────────────────

    /** Adónde enviar al apoderado para que pague en línea. Null si no se puede. */
    public function urlDePago(SolicitudMatricula $solicitud): ?string
    {
        if (! $solicitud->pendiente()) {
            return null;
        }

        $solicitud->loadMissing('alumno');
        $cobro = $this->pasarela->crearCobro(
            $solicitud->referencia,
            "Matrícula {$solicitud->anio} - {$solicitud->alumno->nombreCompleto()}",
            $solicitud->monto,
        );

        if ($cobro === null) {
            return null;
        }

        $solicitud->update(['pasarela_id' => $cobro['id']]);

        return $cobro['url'];
    }

    /**
     * La pasarela dice que hubo un pago: se le consulta y, si está aprobado y
     * completo, se registra y se matricula. Llamarlo dos veces con el mismo
     * pago no lo registra dos veces.
     */
    public function procesarPagoExterno(string $idPago): ?SolicitudMatricula
    {
        $externo = $this->pasarela->consultarPago($idPago);

        if ($externo === null || ! $externo['aprobado'] || $externo['referencia'] === '') {
            return null;
        }

        return DB::transaction(function () use ($externo, $idPago) {
            $solicitud = SolicitudMatricula::query()->where('referencia', $externo['referencia'])->lockForUpdate()->first();

            if ($solicitud === null || ! $solicitud->pendiente()) {
                return $solicitud;
            }

            $concepto = $this->concepto();

            if ($concepto === null || $solicitud->monto > $externo['monto'] + 0.001) {
                Log::warning('Matrícula en línea: pago que no se puede aplicar', ['pago' => $idPago, 'solicitud' => $solicitud->id]);

                return null;
            }

            $pago = Pago::query()->create([
                'alumno_id' => $solicitud->alumno_id,
                'concepto_id' => $concepto->id,
                'anio' => $solicitud->anio,
                'monto' => $externo['monto'],
                'fecha' => today()->toDateString(),
                'metodo' => MetodoPago::EnLinea,
                'numero_operacion' => $idPago,
                'observacion' => 'Pago en línea (Mercado Pago)',
            ]);

            $solicitud->update(['pago_externo_id' => $idPago]);
            $this->confirmar($solicitud, $pago);

            return $solicitud->refresh();
        });
    }

    /** Secretaría registró un pago: si era la matrícula que alguien pidió, se confirma. */
    public function alRegistrarPago(Pago $pago): void
    {
        $pago->loadMissing('concepto');

        if ($pago->concepto->tipo !== TipoConcepto::Matricula) {
            return;
        }

        $solicitud = SolicitudMatricula::query()
            ->where(['alumno_id' => $pago->alumno_id, 'anio' => $pago->anio, 'estado' => SolicitudMatricula::PENDIENTE])
            ->first();

        if ($solicitud !== null) {
            $this->confirmar($solicitud, $pago);
        }
    }

    // ── Confirmar ────────────────────────────────────────────────────────────

    private function confirmar(SolicitudMatricula $solicitud, Pago $pago): void
    {
        DB::transaction(function () use ($solicitud, $pago) {
            $alumno = Alumno::query()->findOrFail($solicitud->alumno_id);

            // Si secretaría ya lo matriculó a mano, se respeta esa matrícula.
            $matricula = Matricula::query()->where(['alumno_id' => $alumno->id, 'anio' => $solicitud->anio])->first();

            if ($matricula === null) {
                $seccion = $this->seccionConMasCupo($solicitud->grado_id, $solicitud->anio);

                if ($seccion === null) {
                    Log::warning('Matrícula en línea: el grado no tiene secciones activas', ['solicitud' => $solicitud->id]);

                    return;
                }

                $matricula = Matricula::query()->create([
                    'alumno_id' => $alumno->id,
                    'seccion_id' => $seccion->id,
                    'anio' => $solicitud->anio,
                    'fecha' => today()->toDateString(),
                    'observacion' => 'Matrícula en línea',
                ]);

                // La matrícula del año en curso define el aula actual del alumno.
                if ($solicitud->anio === now()->year) {
                    $alumno->update(['seccion_id' => $seccion->id]);
                }
            }

            $solicitud->update(['estado' => SolicitudMatricula::MATRICULADO, 'pago_id' => $pago->id, 'matricula_id' => $matricula->id]);

            $this->mensajero->avisarApoderadosDe(
                $alumno,
                TipoMensaje::Pago,
                'Matrícula confirmada',
                sprintf('%s quedó matriculado en %s para el año %d. Pago recibido: S/ %s.',
                    $alumno->nombreCompleto(), $matricula->seccion->loadMissing('grado')->nombreCompleto(),
                    $solicitud->anio, number_format((float) $pago->monto, 2)),
            );
        });
    }

    /** Reparte a los alumnos: va a la sección del grado con menos matriculados ese año. */
    private function seccionConMasCupo(int $gradoId, int $anio): ?Seccion
    {
        $secciones = Seccion::query()->where(['grado_id' => $gradoId, 'activo' => true])->orderBy('nombre')->get();
        $ocupacion = DB::table('matriculas')->where('anio', $anio)->whereIn('seccion_id', $secciones->modelKeys())
            ->selectRaw('seccion_id, count(*) as total')->groupBy('seccion_id')->pluck('total', 'seccion_id');

        return $secciones->sortBy(fn (Seccion $seccion) => (int) ($ocupacion[$seccion->id] ?? 0))->first();
    }

    private function rechazar(string $motivo): never
    {
        throw ValidationException::withMessages(['matricula' => $motivo]);
    }
}
