<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Materia;
use App\Models\TipoDocumento;
use App\Models\Usuario;

/**
 * Quién puede tocar los catalogos del estudio.
 *
 * Solo el administrador los cambia. Un abogado puede proponer una materia nueva
 * escribiéndoselo al socio, pero cambiarla desde la pantalla sería una forma
 * discreta de renombrar el historial de causas ya clasificadas.
 */
class CatalogoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function crear(Usuario $usuario): bool
    {
        return $usuario->rol->administraEstudio();
    }

    public function actualizar(Usuario $usuario, Materia|TipoDocumento $registro): bool
    {
        if ($registro->tenant_id !== $usuario->tenant_id) {
            return false;
        }

        return $usuario->rol === RolUsuario::Administrador;
    }
}