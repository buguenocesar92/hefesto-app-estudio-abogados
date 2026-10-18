<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materias: de qué se ocupa cada causa (civil, laboral, familia, penal...).
 *
 * Es catálogo del estudio. El sistema NO sabe qué materias existen ni qué
 * distingue a una de otra en derecho: el estudio las carga y las usa como
 * etiqueta para agrupar y buscar.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            $tabla->string('codigo', 24);
            $tabla->string('nombre', 120);
            $tabla->text('descripcion')->nullable();
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};