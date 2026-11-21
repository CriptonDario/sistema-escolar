<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CambiarClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clave_actual' => ['required', 'string', 'current_password'],
            'clave_nueva' => [
                'required', 'string', 'max:100',
                Password::min(8)->mixedCase()->numbers(),
                // Volver a poner la contraseña inicial no cuenta como cambiarla.
                Rule::notIn([config('colegio.clave_inicial')]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clave_actual.current_password' => 'La contraseña actual no es correcta.',
            'clave_nueva.not_in' => 'No puedes volver a usar la contraseña inicial.',
        ];
    }
}
