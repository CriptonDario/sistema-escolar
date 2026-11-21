<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una sección en un grado (ruta con {grado}) o edición de una
 * existente (ruta con {seccion}).
 */
class GuardarSeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "b" y "B" son la misma sección: se valida y se guarda en mayúsculas. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('nombre')) {
            $this->merge(['nombre' => mb_strtoupper(trim((string) $this->input('nombre')))]);
        }
    }

    public function rules(): array
    {
        $seccion = $this->route('seccion');
        $gradoId = $seccion->grado_id ?? $this->route('grado')?->id;

        return [
            'nombre' => [
                $seccion ? 'sometimes' : 'nullable',
                $seccion ? 'required' : 'nullable',
                'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('secciones', 'nombre')->where('grado_id', $gradoId)->ignore($seccion),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ese grado ya tiene una sección con ese nombre.',
            'nombre.regex' => 'La sección debe ser una letra o un nombre corto sin espacios.',
        ];
    }
}
