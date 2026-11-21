<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function index(): View
    {
        return view('inventario.index', [
            'productos' => Producto::query()->orderByDesc('activo')->orderBy('nombre')->get(),
            'movimientos' => MovimientoInventario::query()->with('producto')->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('productos', 'nombre')],
            'unidad' => ['required', 'string', 'max:20'],
            'stock_minimo' => ['required', 'integer', 'between:0,100000'],
        ], ['nombre.unique' => 'Ya existe un producto con ese nombre.']);

        Producto::query()->create($datos);

        return back()->with('ok', 'Producto creado. Registra una entrada para cargar su stock.');
    }

    /** Registra una entrada o una salida y ajusta el stock en la misma operación. */
    public function mover(Request $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in([MovimientoInventario::ENTRADA, MovimientoInventario::SALIDA])],
            'cantidad' => ['required', 'integer', 'between:1,100000'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $sinStock = DB::transaction(function () use ($datos, $producto, $request) {
            // Se relee con bloqueo: dos salidas simultáneas no pueden dejar el stock en negativo.
            $actual = Producto::query()->whereKey($producto->id)->lockForUpdate()->firstOrFail();
            $cambio = $datos['tipo'] === MovimientoInventario::ENTRADA ? $datos['cantidad'] : -$datos['cantidad'];

            if ($actual->stock + $cambio < 0) {
                return true;
            }

            $actual->forceFill(['stock' => $actual->stock + $cambio])->save();
            $actual->movimientos()->create($datos + ['registrado_por' => $request->user()->id]);

            return false;
        });

        return $sinStock
            ? back()->with('error', "No hay stock suficiente de {$producto->nombre}.")
            : back()->with('ok', 'Movimiento registrado.');
    }

    public function alternar(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => ! $producto->activo]);

        return back()->with('ok', $producto->activo ? 'Producto reactivado.' : 'Producto desactivado.');
    }
}
