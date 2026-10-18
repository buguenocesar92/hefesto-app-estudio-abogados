<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un plazo.
 *
 * `Vencido` lo pone el vigilante SOLO, cuando la fecha pasó. `Cumplido` lo
 * pone una persona. El sistema no sabe si un plazo se cumplió de verdad: sólo
 * que alguien lo marqué así, y queda quién y cuándo.
 */
enum EstadoPlazo: string
{
    case Pendiente = 'pendiente';
    case Cumplido = 'cumplido';
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Cumplido => 'Cumplido',
            self::Vencido => 'Vencido',
        };
    }

    /** Un plazo cumplido ya no se vigila: se cerró, más o menos bien. */
    public function sigueVigilado(): bool
    {
        return $this === self::Pendiente || $this === self::Vencido;
    }
}