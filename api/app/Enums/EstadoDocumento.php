<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un documento dentro del expediente.
 *
 * `Anulado` existe porque en un estudio un documento mal incorporado se anota
 * que está anulado, no se borra: borrar el rastro de que ese documento se
 * estuvo usando es peor que el error original.
 */
enum EstadoDocumento: string
{
    case Vigente = 'vigente';
    case Sustituido = 'sustituido';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Sustituido => 'Sustituido por versión posterior',
            self::Anulado => 'Anulado',
        };
    }
}