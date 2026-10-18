<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente: la persona que contrata al estudio.
 *
 * DATO PERSONAL PROTEGIDO POR LA LEY 19.628.
 *
 * Se guarda lo justo: nombre, documento de identidad y como contactarlo. No se
 * guarda direccion, telefono de terceros ni antecedentes economicos. Si el
 * estudio necesita algo mas, queda en el expediente fisico y se anota en
 * `notas`.
 *
 * Un cliente puede tener muchas causas y en cada una roles distintos: el rol
 * esta en `expediente_partes`, no aca. Ver `ExpedienteParte`.
 *
 * @property string $nombre
 * @property string|null $documento_identidad
 * @property string $tipo
 * @property bool $activo
 */
class Cliente extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'tipo',
        'nombre',
        'documento_identidad',
        'correo',
        'telefono',
        'notas',
        'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }

    /**
     * Todas las participaciones en causas, con su rol en cada una.
     *
     * @return HasMany<ExpedienteParte, $this>
     */
    public function participaciones(): HasMany
    {
        return $this->hasMany(ExpedienteParte::class);
    }
}