<?php

declare(strict_types=1);

namespace App\Services\Documentos;

use App\Enums\EstadoDocumento;
use App\Models\Documento;

/**
 * DETECTA LA COLISIÓN DE VERSIÓN DE UN DOCUMENTO.
 *
 * ÉSTE ES EL MÓDULO QUE ATAQUE EL PROBLEMA REAL DEL ESTUDIO.
 *
 * EL PROBLEMA
 * -----------
 * El expediente vive en un archivador físico. Llega un documento. Hay que
 * asociarlo al expediente correcto, y lo que casi nunca se detecta es que ese
 * documento YA estaba, o que llegó con una versión distinta de la que se
 * tiene. Cuando eso pasa y nadie lo nota, el estudio sigue trabajando sobre un
 * documento que ya no es el vigente, y eso gana o pierde una causa.
 *
 * CÓMO SE DETECTA
 * ---------------
 * Tres datos bastan, y los tres están en la base:
 *
 *  - la CLAVE NATURAL del documento: cómo lo identifica el estudio.
 *  - la HUELLA del contenido: qué tiene adentro (resumen criptográfico
 *    SHA-256 del archivo).
 *  - la VERSIÓN que trae la copia.
 *
 * Con eso, y con la versión vigente del documento que ya estaba, la decisión es
 * una tabla y no un juicio:
 *
 * ┌──────────────────────┬───────────────────┬──────────────────────┬───────────────────────────────┐
 * │ Contenido idéntico   │ Versión que trae  │ Resultado            │ Por qué                       │
 * ├──────────────────────┼───────────────────┼──────────────────────┼───────────────────────────────┤
 * │ Distinto             │ No hay documento  │ NUEVO                │ Primera vez que llega          │
 * │ Igual                │ Mayor             │ VERSIÓN NUEVA        │ Es la evolución esperada       │
 * │ Igual                │ Igual             │ DUPLICADO EXACTO     │ Es la misma copia de nuevo     │
 * │ DISTINTO             │ Igual o menor     │ COLISIÓN             │ El documento volvió cambiado  │
 * └──────────────────────┴───────────────────┴──────────────────────┴───────────────────────────────┘
 *
 * POR QUÉ LA COLISIÓN NO SE RESUELVE AUTOMÁTICAMENTE
 * -------------------------------------------------
 * Porque el sistema no puede saber cuál de las dos copias es la buena. Puede
 * ser que alguien respaldó mal el archivo, o que dos personas recibieran
 * versiones distintas y no lo sepan, o que el estudio no tenga la última copia
 * en el archivador. Cualquier decisión automática de este caso puede dejar al
 * abogado trabajando sobre el documento equivocado.
 *
 * Por eso la colisión NO sobrescribe: levanta una incidencia de severidad
 * alta y avisa. Decide una persona, que puede ir al archivador físico y
 * mirar.
 *
 * ESTA CLASE NO ESCRIBE NADA
 * --------------------------
 * `diagnosticar()` es una función pura: mira y dice. La escritura está en
 * `ReceptorDeDocumento`. Así la regla de colisión se puede probar sola, sin
 * base de datos, y una modificación accidental en la escritura no puede
 * cambiar la decisión.
 */
class DetectorDeColision
{
    /**
     * Decide qué hacer con una copia que llegó.
     *
     * @param  Documento|null  $documentoExistente  El documento con esa clave
     *                                               natural en ese expediente, o
     *                                               null si no había ninguno.
     * @param  string  $huellaRecibida  Resumen SHA-256 del contenido recibido.
     * @param  int  $versionDeclarada  La versión que trae la copia.
     */
    public function diagnosticar(
        ?Documento $documentoExistente,
        string $huellaRecibida,
        int $versionDeclarada,
    ): DiagnosticoRecepcion {
        // Caso 1: nunca se vio este documento en este expediente.
        if ($documentoExistente === null) {
            return DiagnosticoRecepcion::nuevo(
                'El documento no estaba en este expediente. Se registra como versión 1.',
            );
        }

        $contenidoIdentico = hash_equals(
            $documentoExistente->huella_sha256,
            $huellaRecibida,
        );

        // Caso 2: mismo contenido y misma versión. Es la misma copia otra vez.
        //
        // Se compara con `hash_equals` y no con `===`: no es paranoia de
        // seguridad, es que comparar huellas con `===` filtra información por
        // el tiempo de respuesta, y el costo de `hash_equals` es cero.
        if ($contenidoIdentico && $versionDeclarada === $documentoExistente->version_actual) {
            return DiagnosticoRecepcion::duplicadoExacto(
                $documentoExistente,
                sprintf(
                    'La copia recibida es idéntica a la versión %d que ya está vigente '
                    . '(huella %s). No se agregó nada.',
                    $documentoExistente->version_actual,
                    substr($documentoExistente->huella_sha256, 0, 12),
                ),
            );
        }

        // Caso 3: contenido nuevo y versión mayor. Es la evolución esperada.
        if ($versionDeclarada > $documentoExistente->version_actual) {
            return DiagnosticoRecepcion::versionNueva(
                $documentoExistente,
                sprintf(
                    'La copia difiere de la versión %d y trae la versión %d. '
                    . 'Se agrega como versión nueva y la anterior queda como sustituida.',
                    $documentoExistente->version_actual,
                    $versionDeclarada,
                ),
            );
        }

        // Caso 4: LA COLISIÓN.
        //
        // El contenido es distinto y la versión que trae NO es posterior a la
        // vigente. O sea: el mismo documento volvió cambiado, pero sin una
        // versión que explique por qué cambió.
        return DiagnosticoRecepcion::colisionVersion(
            $documentoExistente,
            sprintf(
                'La copia difiere de la versión %d vigente (huella %s contra %s) pero trae la versión %d. '
                . 'Un documento no cambia de contenido sin cambiar de versión, así que hay dos copias distintas '
                . 'del mismo documento y el sistema no sabe cuál es la buena. NO se sobrescribió nada: '
                . 'revise el archivador físico y decida.',
                $documentoExistente->version_actual,
                substr($documentoExistente->huella_sha256, 0, 12),
                substr($huellaRecibida, 0, 12),
                $versionDeclarada,
            ),
        );
    }

    /**
     * Busca el mismo contenido en OTRAS causas del estudio.
     *
     * Esta es la otra mitad del problema, y la que más se pasa por alto: el
     * documento llegó bien, es el correcto, pero ya estaba archivado en otra
     * causa. Sin esta comprobación se termina con el mismo contrato en dos
     * carpetas y nadie lo sabe.
     *
     * La RLS ya limita la búsqueda al estudio actual, así que esto nunca ve
     * documentos de otro estudio.
     *
     * @return array{id: string, folio: string}|null
     */
    public function buscarEnOtroExpediente(
        string $huellaRecibida,
        string $expedienteActualId,
    ): ?array {
        $otro = Documento::query()
            ->where('huella_sha256', $huellaRecibida)
            ->where('expediente_id', '!=', $expedienteActualId)
            ->where('estado', '!=', EstadoDocumento::Anulado->value)
            ->first(['expediente_id', 'ruta_archivo']);

        if ($otro === null) {
            return null;
        }

        $folio = $otro->expediente?->folio ?? '(folio desconocido)';

        return ['id' => $otro->expediente_id, 'folio' => $folio];
    }
}