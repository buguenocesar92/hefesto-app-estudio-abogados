<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tarea;
use App\Models\Usuario;

/**
 * Quién puede tocar las horas de un expediente.
 *
 * REGLA
 * -----
 * Cada abogado ve y anota SUS horas. El socio y el administrador ven las de
 * todos.
 *
 * El motivo es que las horas son el ingreso del estudio, y una hoja de horas en
 * la que cualquiera puede escribir es una hoja de horas en la que nadie sabe
 * qué se facturó.
 */
class TareaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Tarea $tarea): bool
    {
        if ($tarea->tenant_id !== $usuario->tenant_id) {
            return false;
        }

        return $usuario->can('view', $tarea->expediente);
    }

    public function crear(Usuario $usuario, Tarea $tarea): bool
    {
        return $this->view($usuario, $tarea);
    }

    public function actualizar(Usuario $usuario, Tarea $tarea): bool
    {
        return $this->view($usuario, $tarea);
    }

    public function eliminar(Usuario $usuario, Tarea $tarea): bool
    {
        return false;
    }
}