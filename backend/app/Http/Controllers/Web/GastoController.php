<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GastoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $desde = $filtros['desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $filtros['hasta'] ?? now()->endOfMonth()->toDateString();

        $enRango = Gasto::query()->whereBetween('fecha', [$desde, $hasta]);

        return view('gastos.index', [
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => (clone $enRango)->vigentes()->sum('monto'),
            'porCategoria' => (clone $enRango)->vigentes()->selectRaw('categoria, SUM(monto) as total')
                ->groupBy('categoria')->orderByDesc('total')->pluck('total', 'categoria'),
            'gastos' => $enRango->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString(),
            'categorias' => Gasto::query()->distinct()->orderBy('categoria')->pluck('categoria'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'categoria' => ['required', 'string', 'max:60'],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        Gasto::query()->create($datos + ['registrado_por' => $request->user()->id]);

        return back()->with('ok', 'Gasto registrado.');
    }

    public function destroy(Gasto $gasto): RedirectResponse
    {
        $gasto->update(['anulado_en' => now()]);

        return back()->with('ok', 'Gasto anulado.');
    }
}
