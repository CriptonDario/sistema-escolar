<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Alumno;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega la foto de un alumno. La usan el panel, el portal de padres y la app.
 *
 * Las fotos no están en la carpeta pública: pasan siempre por aquí, y solo las
 * ve el personal que opera el colegio, el apoderado del alumno y el propio alumno.
 */
class FotoAlumnoController extends Controller
{
    public function __invoke(Request $request, Alumno $alumno): StreamedResponse
    {
        abort_unless($this->puedeVerla($request->user(), $alumno), 403, 'No tienes acceso a esa foto.');
        abort_unless($alumno->foto !== null && Storage::disk('local')->exists($alumno->foto), 404);

        return Storage::disk('local')->response($alumno->foto, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    private function puedeVerla(User $user, Alumno $alumno): bool
    {
        if (! $user->activo) {
            return false;
        }

        return match ($user->rol) {
            Rol::Admin, Rol::Guardia, Rol::Direccion => true,
            Rol::Apoderado => (bool) $user->apoderado?->esResponsableDe($alumno->id),
            Rol::Alumno => $alumno->user_id === $user->id,
            default => false,
        };
    }
}
