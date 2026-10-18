<?php

declare(strict_types=1);

namespace App\Http\Requests\Expedientes;

use App\Models\EstadoExpediente;
use App\Models\Materia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Abrir o editar un expediente.
 *
 * LO QUE NO SE PIDE
 * -----------------
 * El `folio` y el `numero_causa` no se aceptan desde el formulario al editar.
 * El folio lo genera la aplicación porque es interno; el número de causa lo
 * coloca una persona porque es del tribunal.
 */
class AbrirExpedienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        return $expediente === null
            ? $this->user()?->can('create', \App\Models\Expediente::class) ?? false
            : $this->user()?->can('update', $expediente) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],

            // El número de causa es texto y opcional. Si viene, tiene que
            // parecer un número de causa, no cualquier cosa: el sistema no sabe
            // qué formato usa cada tribunal, así que solo se acota el largo.
            'numero_causa' => ['nullable', 'string', 'max:60'],

            'materia_id' => [
                'required',
                'uuid',
                Rule::exists('materias', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],

            'abogado_responsable_id' => [
                'nullable',
                'uuid',
                Rule::exists('abogados', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],

            'cliente_id' => [
                'nullable',
                'uuid',
                Rule::exists('clientes', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],

            'fecha_inicio' => ['required', 'date_format:Y-m-d'],

            'detalle' => ['nullable', 'string', 'max:8000'],

            'partes' => ['sometimes', 'array', 'max:40'],
            'partes.*.cliente_id' => [
                'required',
                'uuid',
                Rule::exists('clientes', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'partes.*.rol' => ['required', Rule::in(array_column(\App\Enums\RolParte::cases(), 'value'))],
            'partes.*.es_externo' => ['nullable', 'boolean'],
            'partes.*.notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'materia_id.required' => 'La causa debe tener una materia.',
            'partes.*.rol.in' => 'El rol de la parte no es válido.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe escribirse como AAAA-MM-DD.',
        ];
    }
}