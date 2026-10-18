<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitacora de accesos: quien abrio que expediente, y cuando.
 *
 * POR QUE ES OBLIGATORIA
 * ----------------------
 * En un estudio de abogados, saber quien consulto un expediente no es una
 * funcion de auditoria: es la diferencia entre una perdida de confianza y un
 * incidente de seguridad con nombre y apellido. Un nombre dentro de un
 * expediente basta para danar a alguien, y la Ley 19.628 obliga a poder
 * demostrar quien accedio a los datos.
 *
 * SOLO SE AGREGAN FILAS
 * ---------------------
 * No hay ruta de actualizacion ni de borrado en toda la aplicacion, y la
 * migracion 2026_10_17_000019 deja la garantia en la base con un disparador que
 * rechaza UPDATE y DELETE.
 *
 * @property string $recurso
 * @property string|null $recurso_id
 * @property string $accion
 * @property string $resumen
 * @property \Illuminate\Support\Carbon|null $ocurrido_en
 */
class BitacoraAcceso extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'recurso',
        'recurso_id',
        'resumen',
        'accion',
        'direccion_ip',
        'agente',
        'ocurrido_en',
    ];

    protected $casts = ['ocurrido_en' => 'datetime'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /** Si este registro corresponde a un intento que fue rechazado. */
    public function fueDenegado(): bool
    {
        return $this->accion === 'acceso_denegado';
    }
}