<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas y horas imputables: el módulo que evita que el estudio no cobre.
 *
 * EL PROBLEMA QUE RESUELVE
 * ------------------------
 * Un estudio vive de las horas. El problema clásico no es que el abogado no
 * trabaje, es que nadie anotó las horas: a fin de mes hay trabajo hecho que no
 * está en ninguna parte, y esa plata no se factura nunca.
 *
 * `minutos_imputados` es un entero. El sistema NO calcula minutos: se escriben
 * los que la persona hizo. Convierte a horas la interfaz, que es una división.
 *
 * `minutos_facturados` existe para separar lo PRESENTADO de lo FACTURADO. Es
 * un estado de cuenta interno: esta aplicación NO emite documentos tributarios
 * ni factura nada ante el Servicio de Impuestos. Lo que se anota acá es lo
 * que el estudio ya emitió por su cuenta, o lo que el estudio tiene previsto facturar.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();
            $tabla->foreignUuid('abogado_id')->constrained('abogados')->restrictOnDelete();

            $tabla->string('titulo', 200);
            $tabla->text('detalle')->nullable();

            $tabla->date('fecha');

            // Minutos, no horas: la media hora se registra sin redondear a la
            // hora entera, que es donde se pierde plata en un estudio.
            $tabla->unsignedInteger('minutos_imputados');

            $tabla->boolean('facturable')->default(true);
            $tabla->unsignedInteger('minutos_facturados')->nullable();
            $tabla->date('fecha_facturacion')->nullable();
            $tabla->string('numero_factura', 40)->nullable();

            $tabla->timestamp('registrado_en')->nullable();
            $tabla->foreignUuid('registrado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'expediente_id']);
            $tabla->index(['tenant_id', 'abogado_id']);
            $tabla->index(['tenant_id', 'fecha']);
            $tabla->index(['tenant_id', 'facturable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};