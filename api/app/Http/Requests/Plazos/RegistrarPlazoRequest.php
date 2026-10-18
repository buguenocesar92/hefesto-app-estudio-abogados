<?php

declare(strict_types=1);

namespace App\Http\Requests\Plazos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registrar un plazo.
 *
 * -------------------------------------------------------------------
 * AQUÍ SE VE, EN UN SOLO ARCHIVO, LA ADVERTENCIA DEL PROYECTO
 * -------------------------------------------------------------------
 *
 * `fecha_vencimiento` es REQUERIDA. No se calcula, no se deriva de otra fecha y
 * no se completa con un valor por omisión. Si el abogado no sabe la fecha, el
 * plazo no se registra. Esa es la decisión: es preferible un plazo ausente a un
 * plazo inventado.
 *
 * `texto_respaldo` también es REQUERIDO, con un mínimo de 30 caracteres. No por
 * formalidad: la razón por la que esa fecha se escribió es lo que el estudio
 * necesita conservar cuando la fecha ya pasó.
 *
 * NO HAY AQUÍ ningún campo de "días de plazo", ni "días para contestar", ni nada
 * que se multiplique. Esos números no están en el sistema porque no se pueden
 * suponer.
 */
class RegistrarPlazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        return $this->user()?->can('registrar', $expediente) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],

            // La escribió la persona. El sistema la vigila.
            'fecha_vencimiento' => ['required', 'date_format:Y-m-d'],

            'texto_respaldo' => ['required', 'string', 'min:30', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_vencimiento.required' => 'Escriba la fecha de vencimiento. El sistema no la calcula.',
            'fecha_vencimiento.date_format' => 'La fecha se escribe como AAAA-MM-DD.',
            'texto_respaldo.required' => 'Escriba por qué vence en esa fecha. Ese texto no se podrá modificar después.',
            'texto_respaldo.min' => 'Explique el respaldo con más detalle: con una palabra no queda registro de por qué se escribió esa fecha.',
        ];
    }
}
