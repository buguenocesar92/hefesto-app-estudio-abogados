<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuracion del estudio: una fila por estudio.
 *
 * LO QUE NO ESTA ACA, Y POR QUE
 * -----------------------------
 * No hay ningun plazo legal en esta tabla. No hay "dias para contestar", ni
 * "dias para apelar", ni nada que dependa de un codigo de procedimiento.
 *
 * Lo que si hay son dos numeros OPERATIVOS, que el socio del estudio elige:
 *
 *  - `dias_alerta_plazo`     : cuantos dias antes de la fecha que el abogado
 *                              escribio, el sistema avisa.
 *  - `dias_alerta_critica`   : cuantos dias antes, el aviso pasa a critico.
 *
 * Son alarmas internas de un estudio, no terminos. El sistema NO calcula el
 * vencimiento de nada: la fecha la ingresa el abogado, con su fundamento, y
 * esta tabla solo dice cuando avisar. Ver docs/05.
 *
 * Si `dias_alerta_plazo` queda en NULL, el sistema NO marca nada. Un "rojo"
 * con un numero inventado le diria al abogado que un plazo se vencio cuando
 * no se vencio. Un tablero con huecos se nota y se corrige.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_estudio', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->unique()->constrained('estudios')->cascadeOnDelete();

            $tabla->string('zona_horaria', 64)->default('America/Santiago');
            $tabla->string('moneda', 8)->default('CLP');
            $tabla->unsignedSmallInteger('valor_hora_imputable')->nullable();

            // Alarmas internas. Sin valor, no se marca nada. Ver el bloque de
            // arriba.
            $tabla->unsignedSmallInteger('dias_alerta_plazo')->nullable();
            $tabla->unsignedSmallInteger('dias_alerta_critica')->nullable();

            $tabla->boolean('avisar_documento_duplicado')->default(false);

            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_estudio');
    }
};