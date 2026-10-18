<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de accesos: quién abrió qué expediente, y cuándo.
 *
 * POR QUÉ ESTA TABLA ES OBLIGATORIA Y NO OPCIONAL
 * ----------------------------------------------
 * En un estudio de abogados, saber quién consultó un expediente no es una
 * función de auditoría: es la diferencia entre una pérdida de confianza y un
 * incidente de seguridad con nombre y apellido. Un nombre en un expediente basta
 * para dañar a alguien, y la Ley 19.628 obliga a poder demostrar quién accedió
 * a los datos.
 *
 * En esta tabla SOLO SE AGREGAN filas. No hay ruta de actualización ni de
 * borrado en toda la aplicación, y `2026_10_17_000017_bloquear_borrado_bitacora`
 * deja la garantía en la base.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_accesos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            $tabla->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            // Se guarda el identificador del recurso y su tipo, sin cascada:
            // la bitácora debe sobrevivir al recurso que registra.
            $tabla->string('recurso', 40);
            $tabla->uuid('recurso_id')->nullable();

            // Texto identificatorio para el registro. Con esto se responde
            // "¿qué pasó con la causa C-1234-2026-4521?" sin abrir la causa.
            $tabla->string('resumen', 300);

            $tabla->string('accion', 32);
            $tabla->string('direccion_ip', 45)->nullable();
            $tabla->string('agente', 300)->nullable();

            $tabla->timestampTz('ocurrido_en')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'usuario_id']);
            $tabla->index(['tenant_id', 'recurso', 'recurso_id']);
            $tabla->index(['tenant_id', 'ocurrido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_accesos');
    }
};