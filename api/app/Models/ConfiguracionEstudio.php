<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuracion del estudio. Una fila por estudio.
 *
 * LO QUE NO HAY ACA, Y POR QUE
 * -----------------------------
 * Ningun plazo legal. Ni "dias para contestar", ni "dias para apelar", ni nada
 * que dependa de un codigo de procedimiento.
 *
 * Lo que hay son dos numeros OPERATIVOS que elige el socio:
 * `dias_alerta_plazo` y `dias_alerta_critica`. Son alarmas internas: cuantos
 * dias antes de la fecha que el abogado escribio aparece un aviso. No son
 * terminos, no son plazos y no tienen nada que ver con la norma.
 *
 * Si `dias_alerta_plazo` queda en NULL, el sistema NO marca nada. Ver docs/05.
 *
 * @property int|null $dias_alerta_plazo
 * @property int|null $dias_alerta_critica
 * @property string $zona_horaria
 * @property string $moneda
 */
class ConfiguracionEstudio extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'zona_horaria',
        'moneda',
        'valor_hora_imputable',
        'dias_alerta_plazo',
        'dias_alerta_critica',
        'avisar_documento_duplicado',
    ];

    protected $casts = [
        'dias_alerta_plazo' => 'integer',
        'dias_alerta_critica' => 'integer',
        'valor_hora_imputable' => 'integer',
        'avisar_documento_duplicado' => 'boolean',
    ];

    /** @return BelongsTo<Estudio, $this> */
    public function estudio(): BelongsTo
    {
        return $this->belongsTo(Estudio::class, 'tenant_id');
    }

    /** Si el estudio configuro una ventana de aviso. */
    public function tieneAlertaDePlazos(): bool
    {
        return $this->dias_alerta_plazo !== null;
    }
}