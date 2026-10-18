<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incidencias documentales: el registro de lo que NO encajó.
 *
 * Cuando una recepción de documento no cuadra, el sistema no la descarta ni la
 * fuerza: la deja constancia acá y avisa. Esta tabla es la respuesta a "¿por
 * qué este documento no quedó guardado?" y a la pregunta más incómoda de un
 * estudio: "¿alguien vio esto y decidió no hacer nada?".
 *
 * TIPOS
 * -----
 *  - `colision_version`         : el mismo documento volvió con contenido
 *                                 distinto y una versión que no corresponde.
 *                                 Es la incidencia grave: alguien tiene dos
 *                                 copias diferentes del mismo documento.
 *  - `duplicado_exacto`         : llegó otra copia idéntica a la vigente. No
 *                                 es un problema, pero queda anotado.
 *  - `duplicado_otro_expediente`: este mismo documento ya estaba asociado a
 *                                 OTRA causa del estudio. Es el aviso de que
 *                                 se archivó en la carpeta equivocada.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencias_documentales', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();

            // Puede ser NULL: si la copia se descartó antes de asociarse a un
            // documento, igual hay que dejar constancia de que se vio.
            $tabla->foreignUuid('documento_id')->nullable()->constrained('documentos')->nullOnDelete();

            $tabla->string('tipo', 32);
            $tabla->string('severidad', 8)->default('media');

            $tabla->string('clave_natural', 160);
            $tabla->char('huella_recibida', 64);
            $tabla->unsignedInteger('version_declarada')->nullable();

            // Si la incidencia es "este documento ya estaba en otra causa", acá
            // queda escrito en qué expediente. No se toca el expediente ajeno: se avisa, y el
            // humano decide.
            $tabla->foreignUuid('expediente_conflictivo_id')->nullable()->constrained('expedientes')->nullOnDelete();

            $tabla->text('descripcion');
            $tabla->boolean('revisada')->default(false);
            $tabla->timestamp('revisada_at')->nullable();
            $tabla->foreignUuid('revisada_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'revisada']);
            $tabla->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencias_documentales');
    }
};