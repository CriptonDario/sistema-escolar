<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Personal;
use App\Support\FotoDePersona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega la foto de un trabajador. La ven quienes operan el colegio y el
 * propio trabajador (en su carné de la app).
 */
class FotoPersonalController extends Controller
{
    public function __invoke(Request $request, Personal $personal): StreamedResponse
    {
        $user = $request->user();
        $puede = $user->activo
            && ($user->tieneRol(Rol::Admin, Rol::Guardia, Rol::Direccion) || $personal->user_id === $user->id);

        abort_unless($puede, 403, 'No tienes acceso a esa foto.');
        abort_unless($personal->foto !== null && Storage::disk(FotoDePersona::DISCO)->exists($personal->foto), 404);

        return Storage::disk(FotoDePersona::DISCO)->response($personal->foto, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
