<?php

namespace App\Http\Requests;

use App\Enums\TipoPermiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $llevaHora = TipoPermiso::tryFrom((string) $this->input('tipo'))?->requiereHora() ?? false;

        return [
            'alumno_id' => ['required', 'integer', Rule::exists('alumnos', 'id')->where('activo', true)],
            'tipo' => ['required', Rule::enum(TipoPermiso::class)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => [$llevaHora ? 'required' : 'nullable', 'date_format:H:i'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'alumno_id.exists' => 'No se encontró un alumno activo.',
            'hora.required' => 'Indica la hora autorizada.',
        ];
    }
}
