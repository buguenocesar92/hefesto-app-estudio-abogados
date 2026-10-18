<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estados de expediente: catálogo del estudio, con su máquina de estados.
 *
 * Un estudio define sus propios estados y declara por cuál se puede pasar a
 * cuál. El sistema NO impone una máquina universal: en un estudio de abogados
 * "en revisión" puede llevar a "en consulta" y en otro no. Lo que el sistema
 * hace es IMPEDIR una transición que el estudio no declaró, no decidir cuál
 * corresponde. Ver `App\Services\Expedientes\MaquinaDeEstadosExpediente`.
 *
 * `es_final` marca los estados en que el expediente ya no se mueve.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_expediente', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            $tabla->string('codigo', 24);
            $tabla->string('nombre', 80);
            $tabla->boolean('es_final')->default(false);
            $tabla->unsignedSmallInteger('posicion')->default(0);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'codigo']);
            $tabla->index(['tenant_id', 'posicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_expediente');
    }
};