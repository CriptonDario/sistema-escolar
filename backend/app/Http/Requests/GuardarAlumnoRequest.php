<?php

namespace App\Http\Requests;

use App\Support\FotoDePersona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarAlumnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $alumno = $this->route('alumno');

        return [
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('alumnos', 'codigo')->ignore($alumno)],
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'dni' => ['nullable', 'string', 'max:15'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'seccion_id' => ['nullable', 'integer', Rule::exists('secciones', 'id')],
            'activo' => ['sometimes', 'boolean'],
            // Con correo, el alumno tiene cuenta para entrar a la app.
            'correo' => ['nullable', 'email', 'max:120', Rule::unique('users', 'usuario')->ignore($alumno?->user_id)],
        ] + FotoDePersona::reglas();
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya hay un alumno con ese código.',
            'codigo.regex' => 'El código solo puede tener letras, números y guiones.',
            'correo.unique' => 'Ese correo ya lo usa otra cuenta.',
        ] + FotoDePersona::mensajes();
    }
}
