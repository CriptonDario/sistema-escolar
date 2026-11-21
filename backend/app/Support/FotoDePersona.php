<?php

namespace App\Support;

use App\Models\Alumno;
use App\Models\Personal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * La foto del carné de un alumno o de un trabajador.
 *
 * Se guardan en el disco privado, no en la carpeta pública: hay fotos de
 * menores. Solo se sirven a quien puede verlas (FotoAlumnoController y
 * FotoPersonalController).
 */
class FotoDePersona
{
    public const DISCO = 'local';

    /** @return array<string, array<int, string>> */
    public static function reglas(): array
    {
        return [
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'quitar_foto' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'foto.image' => 'La foto debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max' => 'La foto no puede pesar más de 3 MB.',
            'foto.uploaded' => 'No se pudo subir la foto. Prueba con una imagen de menos de 3 MB.',
        ];
    }

    /**
     * Guarda la foto nueva o quita la actual, y borra el archivo anterior.
     *
     * @param  array<string, mixed>  $datos  ya validados con reglas()
     */
    public static function guardar(Alumno|Personal $persona, array $datos, string $carpeta): void
    {
        $nueva = $datos['foto'] ?? null;
        $anterior = $persona->foto;

        if ($nueva instanceof UploadedFile) {
            $persona->update(['foto' => $nueva->store("fotos/{$carpeta}", self::DISCO)]);
        } elseif (! empty($datos['quitar_foto']) && $anterior !== null) {
            $persona->update(['foto' => null]);
        } else {
            return;
        }

        if ($anterior !== null) {
            Storage::disk(self::DISCO)->delete($anterior);
        }
    }
}
