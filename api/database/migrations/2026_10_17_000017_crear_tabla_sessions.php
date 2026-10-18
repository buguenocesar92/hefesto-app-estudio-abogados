<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones: la tabla que usa Laravel para el guard `web`.
 *
 * NO lleva `tenant_id` ni Row Level Security, a propósito: se consulta para
 * resolver la sesión antes de saber quién es la persona, o sea antes de que
 * exista un contexto de estudio. Un estudio no puede leer las sesiones de otro
 * porque la tabla no tiene el dato: no hay `tenant_id` que filtrar.
 *
 * Esto es una excepción consciente, no un descuido. Está documentada en
 * docs/03, sección "El punto ciego".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();
            $tabla->foreignUuid('usuario_id')->nullable()->index();
            $tabla->string('direccion_ip', 45)->nullable();
            $tabla->text('agente')->nullable();
            $tabla->text('carga');
            $tabla->integer('ultima_actividad')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};