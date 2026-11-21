<?php

namespace App\Http\Controllers\Web;

use App\Actions\GuardarPersonal;
use App\Enums\TipoPersonal;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPersonalRequest;
use App\Models\Personal;
use App\Services\Cuentas\CreadorDeCuentas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonalController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        $personal = Personal::query()
            ->with('user')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->buscar($texto))
            ->orderByDesc('activo')->orderBy('apellidos')->orderBy('nombres')
            ->paginate(25)->withQueryString();

        return view('personal.index', ['personal' => $personal, 'filtros' => $filtros]);
    }

    /** El carné del docente o trabajador, listo para imprimir a tamaño DNI. */
    public function carne(Personal $personal): View
    {
        return view('personal.carne', ['persona' => $personal]);
    }

    public function create(): View
    {
        return view('personal.form', ['persona' => new Personal, 'tipos' => TipoPersonal::cases()]);
    }

    public function store(GuardarPersonalRequest $request, GuardarPersonal $guardar): RedirectResponse
    {
        $persona = $guardar->ejecutar($request->validated());

        return redirect()->route('personal.index')->with('ok', "{$persona->nombreCompleto()} registrado.");
    }

    public function edit(Personal $personal): View
    {
        return view('personal.form', ['persona' => $personal->load('user'), 'tipos' => TipoPersonal::cases()]);
    }

    public function update(GuardarPersonalRequest $request, Personal $personal, GuardarPersonal $guardar): RedirectResponse
    {
        $guardar->ejecutar($request->validated() + ['activo' => $request->boolean('activo')], $personal);

        return redirect()->route('personal.index')->with('ok', 'Datos actualizados.');
    }

    public function destroy(Personal $personal, CreadorDeCuentas $cuentas): RedirectResponse
    {
        $cuentas->darDeBaja($personal);

        return back()->with('ok', "{$personal->nombreCompleto()} fue dado de baja.");
    }
}
