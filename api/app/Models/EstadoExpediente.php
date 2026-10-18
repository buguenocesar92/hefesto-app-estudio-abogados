<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado de expediente: catalogo del estudio.
 *
 * Cada estado declara por cuales se puede pasar, en `configuracion_estudio`
 * como tabla de transiciones. Ver `MaquinaDeEstadosExpediente`.
 *
 * `es_final` marca los estados en que el expediente ya no se mueve.
 *
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_final
 * @property int $posicion
 */
class EstadoExpediente extends ModeloDelEstudio
{
    protected $fillable = ['tenant_id', 'codigo', 'nombre', 'es_final', 'posicion'];

    protected $casts = ['es_final' => 'boolean', 'posicion' => 'integer'];

    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }
}