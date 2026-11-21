<?php

namespace App\Http\Requests;

use App\Enums\Nivel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarGradoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "b" y "B" son la misma sección: se validan y se guardan en mayúsculas. */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('secciones'))) {
            $this->merge(['secciones' => array_map(
                fn ($nombre) => is_string($nombre) ? mb_strtoupper(trim($nombre)) : $nombre,
                $this->input('secciones'),
            )]);
        }
    }

    public function rules(): array
    {
        $grado = $this->route('grado');

        return [
            'nivel' => [$grado ? 'sometimes' : 'required', Rule::enum(Nivel::class)],
            'nombre' => [
                'required', 'string', 'max:50',
                Rule::unique('grados', 'nombre')
                    ->where('nivel', $this->input('nivel', $grado?->nivel->value))
                    ->ignore($grado),
            ],
            'orden' => ['nullable', 'integer', 'between:0,99'],
            'activo' => ['sometimes', 'boolean'],
            // Solo al crear: las secciones con las que nace el grado.
            'secciones' => ['sometimes', 'array', 'min:1', 'max:26'],
            'secciones.*' => ['required', 'string', 'max:10', 'distinct', 'regex:/^[A-Z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un grado con ese nombre en este nivel.',
            'secciones.*.regex' => 'Cada sección debe ser una letra o un nombre corto sin espacios.',
        ];
    }
}
