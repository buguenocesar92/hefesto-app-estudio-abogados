<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPlazo;
use App\Services\Plazos\NivelPlazo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plazo: una fecha que escribio el abogado, y el texto que la respalda.
 *
 * -------------------------------------------------------------------
 * ESTE SISTEMA NO CALCULA PLAZOS LEGALES. En ningun archivo.
 * -------------------------------------------------------------------
 * No hay columna "fecha de inicio mas dias". No hay funcion que derive un
 * vencimiento. No hay un solo plazo legal escrito en el codigo ni en un archivo
 * de configuracion por omision.
 *
 * La fecha la ingresa el abogado. Si no la sabe, el plazo no se registra: no
 * hay forma de que el sistema se la invente.
 *
 * `texto_respaldo` es obligatorio y no se puede modificar despues. La razon
 * importa mas que la fecha: cuando la fecha ya paso, lo que salva al estudio es
 * poder demostrar por que se escribio asi. La garantia vive en un disparador
 * de la base (migracion 2026_10_17_000018).
 *
 * `dias_alerta` es el snapshot del numero operativo del estudio al crear el
 * plazo. NO es un plazo legal: son los dias de alarma interna que eligio el
 * socio. Si queda en NULL, este plazo no se marca nunca.
 *
 * @property \Illuminate\Support\Carbon $fecha_vencimiento
 * @property string $texto_respaldo
 * @property EstadoPlazo $estado
 * @property int|null $dias_alerta
 * @property int|null $dias_alerta_critica
 * @property string|null $respaldo_copiado_al_expediente
 */
class Plazo extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'titulo',
        'fecha_vencimiento',
        'texto_respaldo',
        'estado',
        'dias_alerta',
        'dias_alerta_critica',
        'cumplido_at',
        'cumplido_por_usuario_id',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'estado' => EstadoPlazo::class,
        'dias_alerta' => 'integer',
        'dias_alerta_critica' => 'integer',
        'cumplido_at' => 'datetime',
        'vencido_detectado_at' => 'datetime',
        'respaldo_copiado_at' => 'datetime',
    ];

    /**
     * `texto_respaldo` y `fecha_vencimiento` NO son rellenables.
     *
     * No aparecen en `$fillable` a proposito: una actualizacion masiva no puede
     * tocarlos ni aunque se le pase el array completo. El disparador de la base
     * es la garantia de verdad; esto es la primera linea.
     */

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Usuario, $this> */
    public function cumplidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cumplido_por_usuario_id');
    }

    /** Compara la fecha escrita con la de hoy. No calcula ningun termino. */
    public function nivel(?string $fechaReferencia = null): NivelPlazo
    {
        $hoy = $fechaReferencia ?? now()->toDateString();

        return NivelPlazo::evaluar(
            $this->fecha_vencimiento instanceof \DateTimeInterface
                ? $this->fecha_vencimiento->format('Y-m-d')
                : (string) $this->fecha_vencimiento,
            $hoy,
            $this->dias_alerta,
            $this->dias_alerta_critica,
        );
    }

    /**
     * Copia el respaldo al expediente.
     *
     * Se hace una vez, al crear el plazo, y queda como texto inmutable. Sirve
     * para que la razon sobreviva aunque el plazo se exporte a otro sistema.
     */
    public function copiarRespaldoAlExpediente(): void
    {
        $this->forceFill([
            'respaldo_copiado_al_expediente' => $this->texto_respaldo,
            'respaldo_copiado_at' => now(),
        ])->save();
    }
}
