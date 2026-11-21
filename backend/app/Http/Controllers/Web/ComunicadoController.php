<?php

namespace App\Http\Controllers\Web;

use App\Actions\PublicarComunicado;
use App\Enums\AlcanceComunicado;
use App\Enums\Nivel;
use App\Http\Controllers\Controller;
use App\Models\Comunicado;
use App\Support\Aulas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComunicadoController extends Controller
{
    public function index(): View
    {
        return view('comunicados.index', [
            'comunicados' => Comunicado::query()->with('seccion.grado')->latest()->paginate(15),
            'niveles' => Nivel::cases(),
            'aulas' => Aulas::activas(),
        ]);
    }

    public function store(Request $request, PublicarComunicado $publicar): RedirectResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'cuerpo' => ['required', 'string', 'max:5000'],
            'alcance' => ['required', Rule::enum(AlcanceComunicado::class)],
            'nivel' => ['nullable', 'required_if:alcance,nivel', Rule::enum(Nivel::class)],
            'seccion_id' => ['nullable', 'required_if:alcance,seccion', 'integer', Rule::exists('secciones', 'id')],
        ], [
            'nivel.required_if' => 'Elige el nivel.',
            'seccion_id.required_if' => 'Elige el aula.',
        ]);

        $comunicado = $publicar->ejecutar($datos, $request->user());

        return back()->with('ok', "Comunicado publicado. Llegó a {$comunicado->destinatarios} apoderado(s) en la app.");
    }
}
