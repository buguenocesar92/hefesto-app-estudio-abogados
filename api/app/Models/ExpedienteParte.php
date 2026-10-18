<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolParte;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La union de una persona con un expediente, CON SU ROL.
 *
 * POR QUE ESTA TABLA EXISTE
 * -------------------------
 * Porque el rol es de la RELACION, no de la persona. La misma persona puede ser
 * demandada en una causa y ser cliente del estudio en otra. Con una columna
 * `rol` en `clientes` habria que duplicar a la persona con dos filas y resolver
 * a mano cual es cual, o elegir un rol y perder el otro.
 *
 * Aca una sola fila de `clientes` participa en diez expedientes con diez roles
 * distintos. Unica por (`expediente`, `cliente`, `rol`).
 *
 * QUE NO SABE ESTE SISTEMA
 * ------------------------
 * No sabe que implican estos roles en derecho. No valida que una demanda tenga
 * un demandado. No comprueba que los roles sean coherentes entre si. Los registra
 * y permite filtrar por ellos. Nada mas.
 *
 * @property RolParte $rol
 * @property bool $es_externo
 */
class ExpedienteParte extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'cliente_id',
        'rol',
        'es_externo',
        'notas',
    ];

    protected $casts = ['rol' => RolParte::class, 'es_externo' => 'boolean'];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}