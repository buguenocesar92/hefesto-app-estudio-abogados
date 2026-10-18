<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estudio de abogados: el TENANT.
 *
 * Unica tabla sin Row Level Security y unico modelo que NO extiende
 * `ModeloDelEstudio`. Se consulta antes del inicio de sesion, cuando todavia
 * no hay contexto. Ver la vista `vw_estudios_publicos` y docs/03.
 *
 * @property string $id
 * @property string $slug
 * @property string $nombre
 * @property bool $activo
 */
class Estudio extends Model
{
    use HasUuids;

    protected $table = 'estudios';

    protected $fillable = ['slug', 'nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    /** @return HasMany<Usuario, $this> */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    /** @return HasMany<Abogado, $this> */
    public function abogados(): HasMany
    {
        return $this->hasMany(Abogado::class);
    }

    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }

    public function configuracion(): ?ConfiguracionEstudio
    {
        return $this->hasOne(ConfiguracionEstudio::class)->first();
    }
}