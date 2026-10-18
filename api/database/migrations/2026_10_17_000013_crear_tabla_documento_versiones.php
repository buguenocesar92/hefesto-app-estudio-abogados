<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones de documento: cada copia que se ha recibido, sin excepción.
 *
 * Esta tabla es la que hace auditable un expediente. Un documento que cambió
 * tres veces tiene tres filas acá, con la huella de cada contenido. Nunca se
 * borra una versión anterior: queda marcada `no vigente`, pero se sigue
 * descargando. Si el estudio necesita demostrar qué documento tenía la semana
 * pasada, la respuesta está acá y no en un archivador.
 *
 * `resultado_recepcion` documenta qué decidió el sistema con cada copia:
 *  - `nuevo`             : primera vez que llega este documento.
 *  - `version_nueva`     : llegó una versión posterior, se agregó.
 *  - `duplicado_exacto`  : misma huella que la vigente. No se agregó nada.
 *  - `colision_version`  : contenido distinto con versión que no corresponde.
 *                           NO se agregó nada. Queda la incidencia.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_versiones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('documento_id')->constrained('documentos')->cascadeOnDelete();

            $tabla->unsignedInteger('version');

            // Huella del contenido de ESTA versión. Permite responder "¿esto es
            // lo mismo que lo que ya tengo?" con una comparación de 64
            // caracteres, sin abrir el archivo.
            $tabla->char('huella_sha256', 64);

            $tabla->string('ruta_archivo', 400);
            $tabla->unsignedBigInteger('tamano_bytes')->nullable();

            $tabla->date('fecha_documento')->nullable();
            $tabla->timestamp('recibido_en')->nullable();

            // Cómo llegó y quién la dejó. Es lo que después explica por qué el
            // documento está ahí y quién se equivocó, si se equivocó.
            $tabla->string('origen', 40)->default('manual');
            $tabla->foreignUuid('registrado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->string('estado', 16)->default('vigente');
            $tabla->string('resultado_recepcion', 24)->default('nuevo');

            $tabla->timestampsTz();

            // Una versión por número, dentro del documento. Si dos copias
            // declaran la misma versión con huellas distintas, esta
            // restricción es la que lo vuelve imposible de guardar en
            // silencio: el sistema tiene que avisar antes.
            $tabla->unique(['documento_id', 'version']);

            $tabla->index(['tenant_id', 'documento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_versiones');
    }
};