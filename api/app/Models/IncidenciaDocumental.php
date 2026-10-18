<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoIncidencia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incidencia documental: el registro de lo que NO encajo.
 *
 * Cuando una recepcion de documento no cuadra, el sistema no la descarta ni la
 * fuerza: deja constancia aca y avisa. Esta tabla responde "¿por que este
 * documento no quedo guardado?" y a la pregunta mas incomoda de un estudio:
 * "¿alguien vio esto y decidio no hacer nada?".
 *
 * @property TipoIncidencia $tipo
 * @property string $severidad
 * @property bool $revisada
 */
class IncidenciaDocumental extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'documento_id',
        'tipo',
        'severidad',
        'clave_natural',
        'huella_recibida',
        'version_declarada',
        'expediente_conflictivo_id',
        'descripcion',
        'revisada',
        'revisada_at',
        'revisada_por_usuario_id',
    ];

    protected $casts = [
        'tipo' => TipoIncidencia::value,
        'revisada' => 'boolean',
        'version_declarada' => 'integer',
        'revisada_at' => 'datetime',
    ];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /**
     * La OTRA causa donde estaba el mismo documento.
     *
     * No se toca: se avisa y decide una persona. Un sistema que moviera
     * documentos entre expedientes por su cuenta cambiaría de carpeta un
     * archivo de una causa sin que nadie lo pidiera.
     *
     * @return BelongsTo<Expediente, $this>
     */
    public function expedienteConflictivo(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_conflictivo_id');
    }

    public function esColision(): bool
    {
        return $this->tipo === TipoIncidencia::ColisionVersion->value;
    }
}