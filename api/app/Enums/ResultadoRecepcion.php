<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué decidió el sistema al recibir una copia de un documento.
 *
 * Cada valor deja constancia en `documento_versiones.resultado_recepcion`, que
 * es lo que después responde "¿por qué este documento no quedó guardado?".
 */
enum ResultadoRecepcion: string
{
    /** Primera vez que llega este documento al expediente. */
    case Nuevo = 'nuevo';

    /** Llegó una versión posterior: se agregó, y la anterior quedó no vigente. */
    case VersionNueva = 'version_nueva';

    /** Misma huella que la versión vigente: es la misma copia. No se agregó nada. */
    case DuplicadoExacto = 'duplicado_exacto';

    /**
     * CONTENIDO DISTINTO con una versión que no corresponde.
     *
     * El mismo documento volvió cambiado. NO se sobrescribió nada: se abrió
     * una incidencia de severidad alta y se avisó.
     */
    case ColisionVersion = 'colision_version';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Nuevo => 'Documento nuevo',
            self::VersionNueva => 'Versión nueva',
            self::DuplicadoExacto => 'Duplicado exacto',
            self::ColisionVersion => 'Colisión de versión',
        };
    }

    /** Si el archivo recibido se conservó como versión del documento. */
    public function seGuardoComoVersion(): bool
    {
        return $this === self::Nuevo || $this === self::VersionNueva;
    }

    /** Si hay que avisarle a alguien. */
    public function requiereAviso(): bool
    {
        return $this === self::ColisionVersion || $this === self::DuplicadoExacto;
    }
}