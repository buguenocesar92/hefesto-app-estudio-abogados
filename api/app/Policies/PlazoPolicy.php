<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoPlazo;
use App\Models\Plazo;
use App\Models\Usuario;

/**
 * Quién puede tocar un plazo.
 *
 * HEREDA DEL EXPEDIENTE
 * ---------------------
 * Un plazo sin su expediente no significa nada, así que los permisos son los de
 * la causa.
 *
 * MARCAR COMO CUMPLIDO
 * --------------------
 * Puede hacerlo cualquiera que vea la causa, incluido el pasante: quien estuvo
 * en la reunión sabe si se cumplió, y no tiene sentido preguntarle al socio.
 *
 * LO QUE NO SE PUEDE HACER EN NINGUN CASO
 * ---------------------------------------
 *  - Borrar el plazo. Un plazo que se pasó no se borra: se ve, y por eso la
 *    razon por la que se registró tiene que seguir ahi.
 *  - Cambiar el texto de respaldo o la fecha. No hay Policy que lo permita y hay
 *    un disparador en la base que lo impide.
 */
class PlazoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Plazo $plazo): bool
    {
        return $this->veElPlazo($usuario, $plazo);
    }

    public function crear(Usuario $usuario, Plazo $plazo): bool
    {
        return $this->veElPlazo($usuario, $plazo);
    }

    /** Marcar como cumplido. */
    public function cumplir(Usuario $usuario, Plazo $plazo): bool
    {
        return $this->veElPlazo($usuario, $plazo) && $plazo->estado !== EstadoPlazo::Cumplido;
    }

    public function eliminar(Usuario $usuario, Plazo $plazo): bool
    {
        return false;
    }

    private function veElPlazo(Usuario $usuario, Plazo $plazo): bool
    {
        if ($plazo->tenant_id !== $usuario->tenant_id) {
            return false;
        }

        return $usuario->can('view', $plazo->expediente);
    }
}