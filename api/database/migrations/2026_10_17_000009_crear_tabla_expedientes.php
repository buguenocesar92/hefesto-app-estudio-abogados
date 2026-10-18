<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expedientes: EL CENTRO DEL SISTEMA.
 *
 * Un expediente es una causa. De él cuelgan las partes, los plazos, los
 * documentos y las horas. Si hay que explicar este sistema en una frase, es
 * esto: el expediente es donde llega todo y donde se ve si algo no encaja.
 *
 * SOBRE EL NÚMERO DE CAUSA
 * -----------------------
 * El `numero_causa` lo lleva el tribunal o el estudio y lo coloca alguien a
 * mano. Este sistema NO genera números de causa: inventar un número de causa
 * es exactamente el tipo de dato que un sistema no puede suponer.
 *
 * SOBRE EL ROL DEL ABOGADO
 * -----------------------
 * `abogado_responsable_id` define la CARTERA. Un abogado con rol `abogado`
 * sólo ve los expedientes de los que es responsable. Un socio ve todos los de
 * su estudio. Ver `App\Policies\ExpedientePolicy`.
 *
 * `estado_id` apunta al catálogo `estados_expediente`, que es del estudio.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            // Interno del estudio. Este sí lo genera la aplicación, porque no
            // significa nada fuera de acá.
            $tabla->string('folio', 24);

            // El número de causa va en texto, no en entero: los números de
            // causa traen letras, guiones y barras ("C-1234-2026-4521").
            $tabla->string('numero_causa', 60)->nullable();

            $tabla->foreignUuid('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $tabla->foreignUuid('materia_id')->constrained('materias')->restrictOnDelete();
            $tabla->foreignUuid('abogado_responsable_id')->nullable()->constrained('abogados')->nullOnDelete();
            $tabla->foreignUuid('estado_id')->nullable()->constrained('estados_expediente')->nullOnDelete();

            $tabla->string('titulo', 200);
            $tabla->text('detalle')->nullable();

            $tabla->date('fecha_inicio');
            $tabla->date('fecha_cierre')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'folio']);
            $tabla->index(['tenant_id', 'abogado_responsable_id']);
            $tabla->index(['tenant_id', 'estado_id']);
            $tabla->index(['tenant_id', 'materia_id']);
            $tabla->index(['tenant_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};