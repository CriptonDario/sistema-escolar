<?php

namespace App\Http\Controllers\Web;

use App\Enums\EscalaCalificacion;
use App\Enums\Nivel;
use App\Http\Controllers\Controller;
use App\Support\Ajustes;
use App\Support\Escalas;
use App\Support\Periodos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Configuración del colegio: identidad (nombre, logo, color) y reglas de asistencia.
 */
class AjusteController extends Controller
{
    public function editar(Ajustes $ajustes, Periodos $periodos, Escalas $escalas): View
    {
        return view('ajustes.editar', [
            'niveles' => Nivel::cases(),
            'opcionesEscala' => EscalaCalificacion::cases(),
            'escalaDe' => fn (Nivel $nivel) => $escalas->del($nivel),
            'periodosPorAnio' => $periodos->cantidad(),
            'opcionesPeriodos' => Periodos::opciones(),
            'horaEntrada' => $ajustes->horaEntrada(),
            'tolerancia' => $ajustes->toleranciaMinutos(),
            'minutosSalida' => $ajustes->minutosMinimosParaSalida(),
        ]);
    }

    public function actualizar(Request $request, Ajustes $ajustes, Escalas $escalas): RedirectResponse
    {
        $datos = $request->validate([
            'nombre_colegio' => ['required', 'string', 'max:120'],
            'color_primario' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'hora_entrada' => ['required', 'date_format:H:i'],
            'tolerancia_minutos' => ['required', 'integer', 'between:0,120'],
            'minutos_minimos_para_salida' => ['required', 'integer', 'between:0,600'],
            'periodos_por_anio' => ['required', 'integer', 'in:2,3,4'],
            'escalas' => ['required', 'array:inicial,primaria,secundaria'],
            'escalas.*' => ['required', Rule::enum(EscalaCalificacion::class)],
        ], [
            'color_primario.regex' => 'Elige un color válido.',
            'logo.max' => 'El logo no debe pesar más de 1 MB.',
        ]);

        foreach (['nombre_colegio', 'color_primario', 'hora_entrada', 'tolerancia_minutos', 'minutos_minimos_para_salida', 'periodos_por_anio'] as $clave) {
            $ajustes->guardar($clave, (string) $datos[$clave]);
        }

        foreach ($datos['escalas'] as $nivel => $escala) {
            $escalas->guardar(Nivel::from($nivel), EscalaCalificacion::from($escala));
        }

        if ($request->hasFile('logo')) {
            // Nombre propio y extensión tomada del contenido real del archivo,
            // nunca del nombre que mandó el navegador.
            $archivo = $request->file('logo');
            $nombre = 'logo-'.Str::random(12).'.'.$archivo->extension();
            $archivo->move(public_path('images/marca'), $nombre);

            $ajustes->guardar('logo_url', 'images/marca/'.$nombre);
        }

        return back()->with('ok', 'Configuración guardada.');
    }
}
