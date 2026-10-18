<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarea con horas imputables: el modulo que evita que el estudio no cobre.
 *
 * EL PROBLEMA QUE RESUELVE
 * ------------------------
 * El problema clasico de un estudio no es que el abogado no trabaje: es que
 * nadie anoto las horas. A fin de mes hay trabajo hecho que no esta en ninguna
 * parte, y esa plata no se factura nunca.
 *
 * `minutos_imputados` se escriben. El sistema NO calcula minutos: solo convierte
 * a horas la interfaz, que es una division.
 *
 * LO QUE ESTA APLICACION NO HACE
 * -------------------------------
 * No emite documentos tributarios, no timbre y no factura ante el Servicio de
 * Impuestos, y no habra codigo que lo haga. `minutos_facturados` es un estado
 * de cuenta interno: lo que el estudio ya emitio por su cuenta, o lo que tiene
 * previsto emitir.
 *
 * @property int $minutos_imputados
 * @property int|null $minutos_facturados
 * @property bool $facturable
 * @property \Illuminate\Support\Carbon $fecha
 */
class Tarea extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'abogado_id',
        'titulo',
        'detalle',
        'fecha',
        'minutos_imputados',
        'facturable',
        'minutos_facturados',
        'fecha_facturacion',
        'numero_factura',
        'registrado_por_usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'minutos_imputados' => 'integer',
        'minutos_facturados' => 'integer',
        'facturable' => 'boolean',
        'fecha_facturacion' => 'date',
    ];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Abogado, $this> */
    public function abogado(): BelongsTo
    {
        return $this->belongsTo(Abogado::class);
    }

    /** Horas decimales, para mostrar. Es una division, no un calculo de plazo. */
    public function horas(): float
    {
        return round($this->minutos_imputados / 60, 2);
    }

    /** Si las horas ya fueron cargadas a facturacion. */
    public function facturada(): bool
    {
        return $this->minutos_facturados !== null;
    }
}