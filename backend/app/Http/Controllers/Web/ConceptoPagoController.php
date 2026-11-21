<?php

namespace App\Http\Controllers\Web;

use App\Enums\TipoConcepto;
use App\Http\Controllers\Controller;
use App\Models\ConceptoPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConceptoPagoController extends Controller
{
    public function index(): View
    {
        return view('conceptos.index', [
            'conceptos' => ConceptoPago::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            'tipos' => TipoConcepto::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ConceptoPago::query()->create($this->validar($request));

        return back()->with('ok', 'Concepto creado.');
    }

    public function update(Request $request, ConceptoPago $concepto): RedirectResponse
    {
        $concepto->update($this->validar($request, $concepto));

        return back()->with('ok', 'Concepto actualizado.');
    }

    public function alternar(ConceptoPago $concepto): RedirectResponse
    {
        $concepto->update(['activo' => ! $concepto->activo]);

        return back()->with('ok', $concepto->activo ? 'Concepto reactivado.' : 'Concepto desactivado.');
    }

    private function validar(Request $request, ?ConceptoPago $concepto = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('conceptos_pago', 'nombre')->ignore($concepto)],
            'tipo' => ['required', Rule::enum(TipoConcepto::class)],
            'monto' => ['required', 'numeric', 'between:0,99999'],
        ], ['nombre.unique' => 'Ya existe un concepto con ese nombre.']);
    }
}
