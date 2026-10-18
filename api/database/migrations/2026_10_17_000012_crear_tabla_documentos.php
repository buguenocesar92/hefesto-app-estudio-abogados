<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos: el módulo que ataca el problema real del estudio.
 *
 * EL PROBLEMA QUE ESTE MÓDULO RESUELVE
 * -----------------------------------
 * El expediente vive en un archivador físico. Llega un documento por correo o
 * por mano. Hay que asociarlo al expediente correcto, y lo que casi nunca se
 * detecta es que ese documento YA estaba, o que llegó con una versión distinta
 * de la que se tiene. Cuando eso pasa y nadie lo nota, el estudio trabaja
 * sobre un documento que ya no es el vigente.
 *
 * POR QUÉ NO SE SOBRESCRIBE NADA
 * -----------------------------
 * Un `documento` es la identidad estable de un documento dentro de un
 * expediente. Las copias que llegan son VERSIONES de ese documento, y cada una
 * queda registrada en `documento_versiones`. Nunca se pisa una versión con otra:
 * si una copia llega con contenido distinto y una versión que no corresponde,
 * el sistema NO la guarda encima: levanta una incidencia y avisa. Ver
 * `App\Services\Documentos\ReceptorDeDocumento`.
 *
 * LAS TRES COLUMNAS QUE HACEN LA COLISIÓN DETECTABLE
 * -------------------------------------------------
 *  - `clave_natural`: cómo identifica el estudio a este documento. Puede ser
 *    el número de un contrato, el folio de una presentación, el identificador
 *    que trae el archivo. Es el "de qué estamos hablando".
 *  - `huella_sha256`: la huella del CONTENIDO de la versión vigente. Es el
 *    "qué tiene adentro".
 *  - `version_actual`: qué versión del documento se considera vigente.
 *
 * Con las tres se puede distinguir, sin ambigüedad:
 *
 *   misma clave + misma huella  = el mismo documento, otra copia
 *   misma clave + huella nueva + versión mayor = versión nueva, se agrega
 *   misma clave + huella nueva + versión igual o menor = COLISIÓN
 *
 * La colisión es el caso peligroso: el mismo documento "vuelve" cambiado. O
 * el estudio tiene dos copias distintas del mismo documento y no sabe cuál
 * manda, o alguien está respaldando un documento con el número de versión mal
 * puesto. En cualquier caso, decidir a ciegas es peor que avisar.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $tabla->foreignUuid('tipo_documento_id')->nullable()->constrained('tipos_documento')->nullOnDelete();

            // Identidad estable del documento dentro del expediente. No puede
            // ser NULL: sin esto no hay forma de detectar que "el mismo
            // documento volvió".
            $tabla->string('clave_natural', 160);

            $tabla->string('titulo', 200);

            // Contenido de la versión vigente. Es una huella (resumen
            // criptográfico SHA-256), no el archivo: el archivo va al disco y
            // acá queda la prueba de que no cambió.
            $tabla->char('huella_sha256', 64);

            $tabla->unsignedInteger('version_actual')->default(1);

            $tabla->date('fecha_documento')->nullable();

            // Ruta relativa dentro de DOCUMENTOS_PATH. Relativa a propósito:
            // una ruta absoluta cambiaría al mover el volumen, y el respaldo
            // dejaría de encontrar los archivos.
            $tabla->string('ruta_archivo', 400);

            $tabla->string('estado', 16)->default('vigente');

            $tabla->timestampsTz();

            // La identidad del documento: un documento por clave natural en
            // cada expediente. Esta restricción es la que hace imposible que
            // dos documentos distintos compartan clave en una misma causa, y
            // por eso la colisión se puede detectar siempre.
            $tabla->unique(['expediente_id', 'clave_natural']);

            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'huella_sha256']);
            $tabla->index(['tenant_id', 'tipo_documento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};