<?php

namespace App\Http\Requests;

use App\Enums\TipoPersonal;
use App\Support\FotoDePersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personal = $this->route('personal');

        return [
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('personal', 'codigo')->ignore($personal)],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'dni' => ['nullable', 'string', 'max:15'],
            'cargo' => ['nullable', 'string', 'max:80'],
            'tipo' => ['required', Rule::enum(TipoPersonal::class)],
            'activo' => ['sometimes', 'boolean'],
            // Con correo, tiene cuenta para entrar a la app.
            'correo' => ['nullable', 'email', 'max:120', Rule::unique('users', 'usuario')->ignore($personal?->user_id)],
        ] + FotoDePersona::reglas();
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya hay un trabajador con ese código.',
            'codigo.regex' => 'El código solo puede tener letras, números y guiones.',
            'correo.unique' => 'Ese correo ya lo usa otra cuenta.',
        ] + FotoDePersona::mensajes();
    }
}
