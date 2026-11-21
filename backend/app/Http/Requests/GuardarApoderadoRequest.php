<?php

namespace App\Http\Requests;

use App\Enums\Parentesco;
use App\Models\Apoderado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarApoderadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** El teléfono se valida y se guarda ya normalizado (solo dígitos). */
    protected function prepareForValidation(): void
    {
        if ($this->has('telefono')) {
            $this->merge(['telefono' => Apoderado::normalizarTelefono((string) $this->input('telefono'))]);
        }
    }

    public function rules(): array
    {
        $apoderado = $this->route('apoderado');

        return [
            'nombres' => ['required', 'string', 'max:120'],
            'telefono' => [
                'required', 'string', 'digits_between:7,15',
                Rule::unique('apoderados', 'telefono')->ignore($apoderado),
                Rule::unique('users', 'usuario')->ignore($apoderado?->user_id),
            ],
            'correo' => ['nullable', 'email', 'max:120'],
            'dni' => ['nullable', 'string', 'max:15'],
            // Solo al crear: hijos a vincular en el mismo paso.
            'hijos' => ['sometimes', 'array'],
            'hijos.*.alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')],
            'hijos.*.parentesco' => ['nullable', Rule::enum(Parentesco::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.unique' => 'Ya existe una cuenta con ese teléfono.',
            'telefono.digits_between' => 'Escribe un teléfono válido.',
        ];
    }
}
