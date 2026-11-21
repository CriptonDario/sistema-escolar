<?php

namespace App\Http\Controllers\Web;

use App\Enums\Nivel;
use App\Http\Controllers\Controller;
use App\Models\Materia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MateriaController extends Controller
{
    public function index(): View
    {
        return view('materias.index', [
            'materias' => Materia::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            'niveles' => Nivel::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Materia::query()->create($this->validar($request));

        return back()->with('ok', 'Materia creada.');
    }

    public function update(Request $request, Materia $materia): RedirectResponse
    {
        $materia->update($this->validar($request, $materia));

        return back()->with('ok', 'Materia actualizada.');
    }

    public function alternar(Materia $materia): RedirectResponse
    {
        $materia->update(['activo' => ! $materia->activo]);

        return back()->with('ok', $materia->activo ? 'Materia reactivada.' : 'Materia desactivada.');
    }

    private function validar(Request $request, ?Materia $materia = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('materias', 'nombre')->where('nivel', $request->input('nivel') ?: null)->ignore($materia),
            ],
            'nivel' => ['nullable', Rule::enum(Nivel::class)],
        ], ['nombre.unique' => 'Ya existe esa materia en ese nivel.']);
    }
}
