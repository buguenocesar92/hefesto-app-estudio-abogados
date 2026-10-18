<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de documento: catalogo del estudio.
 *
 * `exige_version` indica si el estudio maneja este tipo de documento con
 * versiones sucesivas. Si esta en falso, la recepcion de una copia nueva del
 * mismo documento se marca como duplicado exacto y no crea versiones.
 *
 * El sistema NO sabe que documentos exige una materia ni que los prueba.
 *
 * @property string $codigo
 * @property string $nombre
 * @property bool $exige_version
 * @property bool $activo
 */
class TipoDocumento extends ModeloDelEstudio
{
    protected $fillable = ['tenant_id', 'codigo', 'nombre', 'exige_version', 'activo'];

    protected $casts = ['exige_version' => 'boolean', 'activo' => 'boolean'];

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }
}