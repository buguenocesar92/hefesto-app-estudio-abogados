<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Cliente;
use App\Models\Usuario;

/**
 * Quién puede ver la lista de clientes.
 *
 * Un cliente tiene causas de distintas materias. El hecho de que un nombre
 * este en la lista de clientes del estudio ya revela que esa persona tiene algo
 * que ver con este despacho, asi que el filtro es estricto.
 *
 *  - Administrador y socio: todos los clientes del estudio.
 *  - Abogado y pasante: SOLO los clientes con al menos una causa en su cartera.
 *
 * El motivo es concreto: un pasante que puede ver el listado completo de
 * clientes ve nombres de personas que no contratan al estudio y cuya causa no
 * lleva. Con el filtro por cartera, solo ve a quienes sus causas tocan.
 */
class ClientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Cliente $cliente): bool
    {
        if ($cliente->tenant_id !== $usuario->tenant_id) {
            return false;
        }

        if ($usuario->rol->veCarteraCompleta()) {
            return true;
        }

        $abogado = $usuario->abogado;

        if ($abogado === null) {
            return false;
        }

        return $cliente->expedientes()
            ->where('abogado_responsable_id', $abogado->id)
            ->exists();
    }

    public function crear(Usuario $usuario): bool
    {
        return true;
    }

    public function actualizar(Usuario $usuario, Cliente $cliente): bool
    {
        return $this->view($usuario, $cliente);
    }
}