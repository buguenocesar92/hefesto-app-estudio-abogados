<?php

declare(strict_types=1);

namespace App\Services\Expedientes;

use App\Models\EstadoExpediente;
use App\Models\Expediente;
use RuntimeException;

/**
 * Maquina de estados del expediente, definida por el estudio.
 *
 * QUE HACE Y QUE NO HACE
 * ----------------------
 * HACE: impide una transicion que el estudio no declaro. Si el catalogo de
 * estados no dice que de "en_revision" se puede pasar a "archivado", el sistema
 * no lo deja.
 *
 * NO HACE: no decide cual es el estado que corresponde. El sistema no sabe si
 * una causa esta lista para archivar; lo sabe el abogado que la lleva. Por eso
 * los estados y las transiciones son del estudio, y el sistema solo obedece.
 *
 * ESTO NO ES UN CALCULO DE PLAZOS
 * -------------------------------
 * Ninguna transicion de esta tabla tiene una cantidad de dias asociada. Que un
 * estado dure quince dias o trescientos es cosa del abogado, y el sistema no lo
 * sugiere.
 */
class MaquinaDeEstadosExpediente
{
    /**
     * Comprueba si la transicion esta declarada.
     *
     * @param  array<string, array<int, string>>  $transiciones  Estado de
     *                                                        destino a
     *                                                        estados
     *                                                        permitidos.
     */
    public function permitida(string $estadoActual, string $estadoDestino, array $transiciones): bool
    {
        return in_array($estadoDestino, $transiciones[$estadoActual] ?? [], true);
    }

    /**
     * Mueve un expediente de estado.
     *
     * @throws RuntimeException si la transicion no esta declarada.
     */
    public function mover(
        Expediente $expediente,
        string $estadoDestino,
        array $transiciones,
    ): Expediente {
        $actual = $expediente->estado?->codigo ?? '';

        if ($actual === $estadoDestino) {
            // Mover a donde ya esta no es un error: la pantalla lo hace
            // sin querer y no vale la pena castigarla con un 422.
        }

        if (! $this->permitida($actual, $estadoDestino, $transiciones)) {
            throw new RuntimeException(
                sprintf(
                    'El estudio no declaró una transición del estado "%s" al estado "%s".',
                    $actual,
                    $estadoDestino,
                ),
            );
        }

        $estado = EstadoExpediente::query()
            ->where('tenant_id', $expediente->tenant_id)
            ->where('codigo', $estadoDestino)
            ->first();

        if ($estado === null) {
            throw new RuntimeException(
                sprintf('El estado "%s" no existe en el catálogo de este estudio.', $estadoDestino),
            );
        }

        $expediente->estado_id = $estado->id;

        if ($estado->es_final) {
            $expediente->fecha_cierre = now()->toDateString();
        } else {
            $expediente->fecha_cierre = null;
        }

        $expediente->save();

        return $expediente;
    }
}
