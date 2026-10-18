<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\EstadoDocumento;
use App\Enums\ResultadoRecepcion;
use App\Enums\TipoIncidencia;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\IncidenciaDocumental;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Recibe una copia de documento y la asocia al expediente.
 *
 * `DetectorDeColision` decide; esta clase escribe. Están separadas para que
 * la regla de colisión se pueda probar sin base de datos, y para que un cambio
 * en el guardado no cambie la decisión.
 *
 * EL COMPROMISO QUE SE ESCRIBE EN TODAS LAS RUTAS
 * ------------------------------------------------
 * Ninguna ruta de esta aplicación borra un documento. Ni una versión, ni una
 * anterior, ni una anulada. Un expediente sin su historial documental no sirve
 * para nada: si hay que demostrar qué se tenía en marzo, la respuesta tiene que
 * estar acá y no en un archivador.
 */
class ReceptorDeDocumento
{
    public function __construct(private readonly DetectorDeColision $detector) {}

    /**
     * Asocia una copia a un expediente.
     *
     * @param  array{
     *     clave_natural: string,
     *     titulo: string,
     *     huella_sha256: string,
     *     ruta_archivo: string,
     *     contenido_binario: string,
     *     tipo_documento_id?: string|null,
     *     fecha_documento?: string|null,
     *     version_declarada: int
     * }  $datos
     * @return array{documento: Documento, diagnostico: DiagnosticoRecepcion, incidencia: ?IncidenciaDocumental}
     */
    public function recibir(array $datos, string $tenantId, ?string $usuarioId = null): array
    {
        return DB::transaction(function () use ($datos, $tenantId, $usuarioId): array {
            $documento = $this->buscarDocumento($datos['expediente_id'] ?? null, $datos['clave_natural']);

            $diagnostico = $this->detector->diagnosticar(
                $documento,
                $datos['huella_sha256'],
                $datos['version_declarada'],
            );

            // Sin cambio en el documento (duplicado exacto o colisión): se
            // guarda el archivo para poder examinarlo, pero NO se toca el
            // documento que ya estaba. Esto es lo que hace que la colisión no
            // sea una sobrescritura encubierta.
            if (! $diagnostico->huboCambioEnElDocumento) {
                $version = $this->guardarCopiaSinCambiarDocumento($datos, $documento, $diagnostico, $tenantId, $usuarioId);

                $incidencia = $diagnostico->dejaIncidencia()
                    ? $this->abrirIncidencia($datos, $diagnostico, $documento, $tenantId)
                    : $this->buscarDuplicadoEnOtroExpediente($datos, $diagnostico, $documento, $tenantId);

                return [
                    'documento' => $documento,
                    'diagnostico' => $diagnostico,
                    'incidencia' => $incidencia,
                    'version_descartada' => $version,
                ];
            }

            $documento = $diagnostico->resultado === ResultadoRecepcion::Nuevo
                ? $this->crearDocumento($datos, $tenantId)
                : $this->agregarVersionNueva($datos, $documento, $tenantId);

            $this->guardarVersion($datos, $documento, $tenantId, $usuarioId, $diagnostico->resultado, true);

            return [
                'documento' => $documento,
                'diagnostico' => $diagnostico,
                'incidencia' => null,
                'version_descartada' => null,
            ];
        });
    }

    private function buscarDocumento(?string $expedienteId, string $claveNatural): ?Documento
    {
        if ($expedienteId === null) {
            return null;
        }

        return Documento::query()
            ->where('expediente_id', $expedienteId)
            ->where('clave_natural', $claveNatural)
            ->first();
    }

    private function crearDocumento(array $datos, string $tenantId): Documento
    {
        return Documento::query()->create([
            'tenant_id' => $tenantId,
            'expediente_id' => $datos['expediente_id'],
            'tipo_documento_id' => $datos['tipo_documento_id'] ?? null,
            'clave_natural' => $datos['clave_natural'],
            'titulo' => $datos['titulo'],
            'huella_sha256' => $datos['huella_sha256'],
            'version_actual' => $datos['version_declarada'],
            'fecha_documento' => $datos['fecha_documento'] ?? null,
            'ruta_archivo' => $datos['ruta_archivo'],
            'estado' => EstadoDocumento::Vigente->value,
        ]);
    }

    /**
     * Versión nueva: la anterior queda SUSTITUIDA, nunca borrada.
     */
    private function agregarVersionNueva(array $datos, Documento $documento, string $tenantId): Documento
    {
        DocumentoVersion::query()
            ->where('documento_id', $documento->id)
            ->where('estado', 'vigente')
            ->update(['estado' => 'no_vigente']);

        $documento->huella_sha256 = $datos['huella_sha256'];
        $documento->version_actual = $datos['version_declarada'];
        $documento->ruta_archivo = $datos['ruta_archivo'];
        $documento->fecha_documento = $datos['fecha_documento'] ?? $documento->fecha_documento;
        $documento->estado = EstadoDocumento::Vigente->value;
        $documento->save();

        return $documento;
    }

    /**
     * Guarda la copia para poder mirarla, pero sin cambiar el documento.
     *
     * En una colisión, esta copia es la evidencia: es el segundo contenido
     * distinto que existe para el mismo documento. Sin guardarla, el estudio
     * tendría que volver al correo donde llegó para poder comparar.
     */
    private function guardarCopiaSinCambiarDocumento(
        array $datos,
        ?Documento $documento,
        DiagnosticoRecepcion $diagnostico,
        string $tenantId,
        ?string $usuarioId,
    ): DocumentoVersion {
        if ($documento === null) {
            throw new RuntimeException(
                'No se puede guardar una copia descartada sin documento de referencia.'
            );
        }

        return $this->guardarVersion(
            $datos,
            $documento,
            $tenantId,
            $usuarioId,
            $diagnostico->resultado,
            false,
        );
    }

    private function guardarVersion(
        array $datos,
        Documento $documento,
        string $tenantId,
        ?string $usuarioId,
        ResultadoRecepcion $resultado,
        bool $vigente,
    ): DocumentoVersion {
        return DocumentoVersion::query()->create([
            'tenant_id' => $tenantId,
            'documento_id' => $documento->id,
            'version' => $datos['version_declarada'],
            'huella_sha256' => $datos['huella_sha256'],
            'ruta_archivo' => $datos['ruta_archivo'],
            'tamano_bytes' => strlen($datos['contenido_binario'] ?? ''),
            'fecha_documento' => $datos['fecha_documento'] ?? null,
            'recibido_en' => now(),
            'origen' => $datos['origen'] ?? 'manual',
            'registrado_por_usuario_id' => $usuarioId,
            'estado' => $vigente ? 'vigente' : 'no_vigente',
            'resultado_recepcion' => $resultado->value,
        ]);
    }

    private function abrirIncidencia(
        array $datos,
        DiagnosticoRecepcion $diagnostico,
        ?Documento $documento,
        string $tenantId,
    ): IncidenciaDocumental {
        $tipo = $diagnostico->resultado === ResultadoRecepcion::ColisionVersion
            ? TipoIncidencia::ColisionVersion
            : TipoIncidencia::DuplicadoExacto;

        return IncidenciaDocumental::query()->create([
            'tenant_id' => $tenantId,
            'expediente_id' => $datos['expediente_id'],
            'documento_id' => $documento?->id,
            'tipo' => $tipo->value,
            'severidad' => $tipo->severidad(),
            'clave_natural' => $datos['clave_natural'],
            'huella_recibida' => $datos['huella_sha256'],
            'version_declarada' => $datos['version_declarada'],
            'descripcion' => $diagnostico->explicacion,
            'revisada' => false,
        ]);
    }

    /**
     * "Este documento ya estaba en otra causa".
     *
     * Es el aviso que más se pasa por alto. El documento llegó bien y es el
     * correcto, pero está archivado en otra carpeta del estudio.
     */
    private function buscarDuplicadoEnOtroExpediente(
        array $datos,
        DiagnosticoRecepcion $diagnostico,
        ?Documento $documento,
        string $tenantId,
    ): ?IncidenciaDocumental {
        $otro = $this->detector->buscarEnOtroExpediente(
            $datos['huella_sha256'],
            $datos['expediente_id'],
        );

        if ($otro === null) {
            return null;
        }

        return IncidenciaDocumental::query()->create([
            'tenant_id' => $tenantId,
            'expediente_id' => $datos['expediente_id'],
            'documento_id' => $documento?->id,
            'tipo' => TipoIncidencia::DuplicadoOtroExpediente->value,
            'severidad' => TipoIncidencia::DuplicadoOtroExpediente->severidad(),
            'clave_natural' => $datos['clave_natural'],
            'huella_recibida' => $datos['huella_sha256'],
            'version_declarada' => $datos['version_declarada'],
            'expediente_conflictivo_id' => $otro['id'],
            'descripcion' => sprintf(
                'El contenido de "%s" (huella %s) ya estaba registrado en el expediente %s. '
                . 'Revise si corresponde a esta causa o si se archivó en la carpeta equivocada. '
                . 'Motivo de la recepción: %s',
                $datos['titulo'] ?? $datos['clave_natural'],
                substr($datos['huella_sha256'], 0, 12),
                $otro['folio'],
                $diagnostico->explicacion,
            ),
            'revisada' => false,
        ]);
    }

    /**
     * Calcula la huella del contenido.
     *
     * Se calcula acá y no en el controlador para que nadie pueda pasar una
     * huella inventada por la API. Si el cliente mandara la huella, podría
     * mandar "la misma" para un contenido distinto y la detección de colisión
     * no serviría para nada.
     */
    public function calcularHuella(string $contenido): string
    {
        return hash('sha256', $contenido);
    }

    /** Ruta relativa dentro de `DOCUMENTOS_PATH`. Relativa a propósito. */
    public function construirRuta(string $expedienteId, string $claveNatural, string $version): string
    {
        $prefijo = Str::of($claveNatural)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->limit(80, '')
            ->value();

        return sprintf('%s/%s-v%s-%s', $expedienteId, $prefijo, $version, Str::random(8));
    }
}