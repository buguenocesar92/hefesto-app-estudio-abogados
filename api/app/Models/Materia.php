<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Materia: de que se ocupa la causa. Catalogo del estudio.
 *
 * El sistema no sabe que materias existen ni que distingue a una de otra en
 * derecho. El estudio las carga y las usa como etiqueta para agrupar y buscar.
 *
 * @property string $codigo
 * @property string $nombre
 * @property bool $activo
 */
class Materia extends ModeloDelEstudio
{
    protected $fillable = ['tenant_id', 'codigo', 'nombre', 'descripcion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }
}