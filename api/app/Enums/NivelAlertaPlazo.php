<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nivel de alerta de un plazo: lo que la pantalla muestra y el color.
 *
 * ADVERTENCIA QUE ESTE ENUM NO ES
 * -------------------------------
 * Ninguno de estos valores significa que un plazo legal se haya vencido o esté
 * por vencerse. Son colores internos de un estudio, comparados contra la fecha
 * que el abogado escribió y el número de días que el socio configuró.
 *
 * En particular `Vencido` significa "la fecha que escribiste ya pasó", y nada
 * más. Que ese vencimiento tenga consecuencia jurídica o no, depende de la
 * norma aplicable y del cómputo que hizo el abogado, y eso no está en el
 * sistema ni puede estarlo.
 *
 * `SinAlerta` existe y es importante: cuando el plazo no tiene número de días
 * configurado, el sistema no marca nada en vez de usar un valor por omisión.
 * Ver docs/05.
 */
enum NivelAlertaPlazo: string
{
    /** Sin número configurado: el sistema no dice nada. */
    case SinAlerta = 'sin_alerta';

    case Vencido = 'vencido';

    case Critico = 'critico';

    case Proximo = 'proximo';

    case EnTiempo = 'en_tiempo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SinAlerta => 'Sin alerta configurada',
            self::Vencido => 'Vencido',
            self::Critico => 'Crítico',
            self::Proximo => 'Próximo',
            self::EnTiempo => 'En tiempo',
        };
    }

    /** Si el sistema está diciendo algo sobre este plazo. */
    public function marcado(): bool
    {
        return $this !== self::SinAlerta;
    }

    /** La interfaz usa esto para el color. Sin colores inventados para `SinAlerta`. */
    public function color(): string
    {
        return match ($this) {
            self::Vencido => 'rojo',
            self::Critico => 'naranja',
            self::Proximo => 'amarillo',
            self::EnTiempo => 'verde',
            self::SinAlerta => 'gris',
        };
    }

    /**
     * Si este nivel corresponde a un plazo que hay que mirar YA.
     *
     * Lo usa el vigilante para decidir si abre un aviso. Un plazo
     * `SinAlerta` nunca se avisa: no hay número con que compararlo.
     */
    public function requiereAviso(): bool
    {
        return $this === self::Vencido || $this === self::Critico;
    }
}