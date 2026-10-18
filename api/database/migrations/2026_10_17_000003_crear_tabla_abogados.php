<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abogados: las personas que ejercen.
 *
 * Un abogado tiene una `matricula` (el registro en el Colegio de Abogados) y
 * puede tener `horas_facturables`. El vinculo con la cuenta de inicio de
 * sesion es `usuario_id`: un abogado puede entrar al sistema, y puede no
 * tener cuenta si trabaja con el estudio pero no usa el sistema.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abogados', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->string('nombre', 120);
            $tabla->string('apellidos', 120);

            // La matricula la lleva el abogado y la escribe el estudio. NO se
            // valida contra ningun registro externo: este sistema no consulta
            // ningun registro del Colegio de Abogados.
            $tabla->string('matricula', 60)->nullable();

            $tabla->unsignedSmallInteger('horas_facturables')->nullable();
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'matricula']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abogados');
    }
};