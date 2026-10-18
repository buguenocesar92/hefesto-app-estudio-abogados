<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Expediente;
use App\Models\Usuario;

/**
 * Quién puede ver cada expediente.
 *
 * DOBLE FILTRO
 * ------------
 * Este es el SEGUNDO filtro, dentro del estudio. El primero es la RLS, que ya
 * garantiza que nada sale del estudio. Este filtra dentro: no todas las causas
 * de un estudio le tocan a todos sus abogados.
 *
 * LA REGLA
 * --------
 *  - El administrador y el socio ven todas las causas del estudio.
 *  - El abogado ve las causas de las que es responsable. Su CARTERA.
 *  - El pasante ve las suyas.
 *
 * POR QUÉ NO SE DEVUELVE 403
 * -------------------------
 * Cuando una Policy rechaza, la respuesta es 404, no 403: un 403 confirmaría
 * que el expediente existe. Y como la RLS impide que el expediente exista a ojos
 * de otro estudio, 404 es además la respuesta correcta en el caso habitual.
 */
class ExpedientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Expediente $expediente): bool
    {
        return $this->veLaCausa($usuario, $expediente);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->rol !== RolUsuario::Pasante;
    }

    public function update(Usuario $usuario, Expediente $expediente): bool
    {
        return $this->veLaCausa($usuario, $expediente)
            && $usuario->rol->cambiaEstadoDeExpediente();
    }

    /**
     * Registrar plazos, documentos y horas.
     *
     * El pasante sí puede. Cargar horas y asociar un documento que llegó es
     * parte de su trabajo, y no tiene sentido impedirlo.
     */
    public function registrar(Usuario $usuario, Expediente $expediente): bool
    {
        return $this->veLaCausa($usuario, $expediente);
    }

    /**
     * NO existe el borrado de expedientes en esta aplicación.
     *
     * La Policy devuelve siempre false y el controlador no expone ruta de
     * borrado. Un expediente mal abierto se ANULA.
     */
    public function eliminar(Usuario $usuario, Expediente $expediente): bool
    {
        return false;
    }

    private function veLaCausa(Usuario $usuario, Expediente $expediente): bool
    {
        // El filtro de estudio ya lo hizo la RLS, pero se repite igual: si el
        // contexto llegara mal puesto, la Policy frena antes que la base.
        if ($expediente->tenant_id !== $usuario->tenant_id) {
            return false;
        }

        if ($usuario->rol->veCarteraCompleta()) {
            return true;
        }

        $abogado = $usuario->abogado;

        if ($abogado === null) {
            // Una cuenta sin ficha de abogado no ve nada. Es el caso raro, y
            // dejarlo pasar sería abrir la cartera a cualquiera.
            return false;
        }

        return $expediente->esCarteraDe($abogado);
    }
}