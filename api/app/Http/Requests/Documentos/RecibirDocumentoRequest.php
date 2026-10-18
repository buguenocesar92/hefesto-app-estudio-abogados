<?php

declare(strict_types=1);

namespace App\Http\Requests\Documentos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recibir una copia de documento.
 *
 * LO QUE SE EXIGE PARA PODER DETECTAR UNA COLISIÓN
 * ------------------------------------------------
 *  - `clave_natural`: cómo identifica el estudio a este documento. Sin esto no
 *    hay forma de saber que "el mismo documento volvió".
 *  - `archivo`: el contenido. La huella se calcula en el servidor y NO se
 *    acepta desde el formulario. Si el cliente mandara la huella, podría
 *    mandar la misma para un contenido distinto y la detección sería decorativa.
 *  - `version_declarada`: qué versión trae la copia. Es el número con el que se
 *    compara contra la versión vigente para decidir si es una versión nueva, un
 *    duplicado o una colisión.
 */
class RecibirDocumentoRequest extends FormRequest
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
            'tipo_documento_id' => [
                'nullable',
                'uuid',
                Rule::exists('tipos_documento', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],

            'clave_natural' => ['required', 'string', 'max:160'],

            'titulo' => ['required', 'string', 'max:200'],

            'version_declarada' => ['required', 'integer', 'min:1', 'max:9999'],

            'fecha_documento' => ['nullable', 'date_format:Y-m-d'],

            'archivo' => ['required', 'file', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clave_natural.required' => 'Indique cómo identifica el estudio a este documento.',
            'version_declarada.required' => 'Indique qué versión trae esta copia.',
            'archivo.max' => 'El archivo supera los 50 MB. Para un documento mayor, ver docs/04.',
        ];
    }

    /**
     * NO SE ACEPTA `huella_sha256` DESDE EL FORMULARIO.
     *
     * Es a propósito: la calcula el servidor. Ver `DocumentoController::store`.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('huella_sha256');
    }
}