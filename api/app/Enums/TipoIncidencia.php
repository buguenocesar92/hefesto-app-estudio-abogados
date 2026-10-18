<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de incidencia documental: por qué una recepción no cuadró.
 *
 * La distinción que más importa es `ColisionVersion` contra las otras dos: la
 * colisión significa que hay DOS COPIAS DIFERENTES del mismo documento y el
 * sistema no sabe cuál manda. Las otras dos son un archivo repetido o mal
 * clasificado, no un problema de fondo.
 */
enum TipoIncidencia: string
{
    /** Dos contenidos distintos con la misma versión declarada. Grave. */
    case ColisionVersion = 'colision_version';

    /** Llegó otra copia idéntica a la vigente. Informativo. */
    case DuplicadoExacto = 'duplicado_exacto';

    /** Este documento ya estaba en otra causa del estudio. */
    case DuplicadoOtroExpediente = 'duplicado_otro_expediente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ColisionVersion => 'Colisión de versión',
            self::DuplicadoExacto => 'Duplicado exacto',
            self::DuplicadoOtroExpediente => 'Ya estaba en otro expediente',
        };
    }

    /**
     * Cuánto pesa. La colisión es `alta` porque significa que el estudio puede
     * estar trabajando sobre una copia que ya no es la buena.
     */
    public function severidad(): string
    {
        return match ($this) {
            self::ColisionVersion => 'alta',
            self::DuplicadoOtroExpediente => 'media',
            self::DuplicadoExacto => 'baja',
        };
    }
}