<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de documento: catálogo del estudio.
 *
 * Ejemplos que carga un estudio: "Contrato", "Poder", "Sentencia",
 * "Carta de solicitud". El sistema NO sabe qué documentos exige una materia ni
 * qué los prueba: sólo los usa para clasificar y para saber qué se está
 * esperando.
 *
 * `exige_version` indica si el estudio maneja este tipo de documento con
 * versiones sucesivas. Si está en falso, la recepción de una copia nueva del
 * mismo documento se marca como duplicado exacto y no crea versiones.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documento', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            $tabla->string('codigo', 24);
            $tabla->string('nombre', 120);
            $tabla->boolean('exige_version')->default(true);
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documento');
    }
};