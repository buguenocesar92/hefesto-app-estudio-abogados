<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Usuario: la persona que entra al sistema.
 *
 * La identidad la trae Authentik (correo, nombre). Aca se guarda solo lo que el
 * estudio necesita saber y Authentik no sabe: a que estudio pertenece y que rol
 * tiene adentro. El rol vive en la base y no en el proveedor, porque el
 * proveedor no sabe de estudios de abogados.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $subject
 * @property string $correo
 * @property string|null $nombre
 * @property RolUsuario $rol
 */
class Usuario extends ModeloDelEstudio
{
    protected $fillable = ['tenant_id', 'subject', 'correo', 'nombre', 'rol'];

    protected $casts = ['rol' => RolUsuario::class];

    /** @return BelongsTo<Estudio, $this> */
    public function estudio(): BelongsTo
    {
        return $this->belongsTo(Estudio::class, 'tenant_id');
    }

    /** @return HasOne<Abogado, $this> */
    public function abogado(): HasOne
    {
        return $this->hasOne(Abogado::class, 'usuario_id');
    }

    /** Si el rol le permite ver toda la cartera del estudio. */
    public function veCarteraCompleta(): bool
    {
        return $this->rol->veCarteraCompleta();
    }
}