<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\ResultadoRecepcion;
use App\Models\Documento;

/**
 * Resultado de mirar una copia que llegó, SIN escribir nada.
 *
 * Es un objeto aparte y deliberadamente tonto: no toca la base. Su único
 * trabajo es decir qué es lo que hay que hacer con la copia, para que esa
 * decisión se pueda probar por separado de la escritura.
 *
 * `huboCambioEnElDocumento` distingue "guardé una versión nueva" de "no guardé
 * nada porque algo no cuadraba". La interfaz y la auditoría lo usan para no
 * mentir: si no hubo cambio, la pantalla dice que no hubo cambio, aunque la
 * operación haya creado una incidencia.
 */
final class DiagnosticoRecepcion
{
    private function __construct(
        public readonly ResultadoRecepcion $resultado,
        public readonly ?Documento $documento,
        public readonly string $explicacion,
        public readonly bool $huboCambioEnElDocumento,
        public readonly ?string $expedienteConflictivoId = null,
        public readonly ?string $expedienteConflictivoFolio = null,
    ) {}

    public static function nuevo(string $explicacion): self
    {
        return new self(ResultadoRecepcion::Nuevo, null, $explicacion, true);
    }

    public static function versionNueva(Documento $documento, string $explicacion): self
    {
        return new self(ResultadoRecepcion::VersionNueva, $documento, $explicacion, true);
    }

    public static function duplicadoExacto(Documento $documento, string $explicacion): self
    {
        return new self(
            ResultadoRecepcion::DuplicadoExacto,
            $documento,
            $explicacion,
            false,
        );
    }

    /**
     * LA COLISIÓN.
     *
     * El documento volvió con contenido distinto del que se tiene, y la
     * versión que trae no corresponde. Se devuelve `huboCambio: false` a
     * propósito: no se escribió nada sobre el documento que ya estaba.
     */
    public static function colisionVersion(
        Documento $documento,
        string $explicacion,
    ): self {
        return new self(
            ResultadoRecepcion::ColisionVersion,
            $documento,
            $explicacion,
            false,
        );
    }

    /** Si hay que dejar constancia en `incidencias_documentales`. */
    public function dejaIncidencia(): bool
    {
        return $this->resultado->requiereAviso();
    }
}