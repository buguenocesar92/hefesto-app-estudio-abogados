<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazos: LA ADVERTENCIA QUE DEFINE ESTE PROYECTO.
 *
 * Un estudio de abogados trabaja con plazos computados según normativa y
 * códigos de procedimiento, y esos textos cambian. Si este sistema inventa un
 * plazo, el abogado pierde un término, y un término perdido no se recupera.
 *
 * POR QUÉ NO HAY CÁLCULO AQUÍ
 * --------------------------
 * No existe ninguna columna del tipo "fecha de inicio más cantidad de días".
 * No hay en toda la aplicación una función que calcule un vencimiento. No hay
 * ni un solo plazo legal escrito en el código, ni en un archivo de
 * configuración por omisión.
 *
 * Lo que hay es:
 *
 *  - `fecha_vencimiento`: la fecha. La INGRESA el abogado. No la calcula
 *    nadie. Si no la sabe, el plazo no se registra: no hay forma de que el
 *    sistema se la invente.
 *
 *  - `texto_respaldo`: por qué vence ese día. Es OBLIGATORIO, y es lo más
 *    importante de esta tabla. La razón importa más que la fecha: cuando la
 *    fecha ya pasó, lo que salva al estudio es poder demostrar por qué se
 *    registró así. Por eso el texto se copia al expediente y ahí queda
 *    inmutable (ver la migración `2026_10_17_000018_inmovilizar_respaldo_plazo`).
 *
 * LO QUE EL SISTEMA HACE: VIGILAR
 * -------------------------------
 * Compara `fecha_vencimiento` con la fecha de hoy y avisa. Eso es todo. La
 * comparación es aritmética de calendario, no una regla jurídica: el sistema
 * no sabe si ese plazo corre en días hábiles o corridos, porque eso lo sabe el
 * abogado y ya lo dejó escrito en `texto_respaldo`.
 *
 * `dias_alerta` es el SNAPSHOT del número operativo del estudio al crear el
 * plazo (ver `configuracion_estudio`). Si queda en NULL, este plazo NO se
 * marca nunca: no hay con qué compararlo, y un "rojo" con un número inventado
 * sería peor que no avisar. Ver `App\Services\Plazos\VigilanteDePlazos`.
 *
 * `estado` es `pendiente`, `cumplido` o `vencido`. El estado `vencido` lo
 * pone el vigilante solo cuando la fecha pasó; `cumplido` lo pone una persona.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plazos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();

            $tabla->string('titulo', 200);

            // La fecha la escribe el abogado. El sistema no la deriva de nada.
            $tabla->date('fecha_vencimiento');

            // LA RAZÓN. Obligatoria desde la base, no desde el formulario: si
            // el formulario se puede saltear, la base lo impide.
            $tabla->text('texto_respaldo');

            $tabla->string('estado', 16)->default('pendiente');

            // Snapshot del número operativo del estudio al crear el plazo.
            $tabla->unsignedSmallInteger('dias_alerta')->nullable();
            $tabla->unsignedSmallInteger('dias_alerta_critica')->nullable();

            // Cuándo se detectó el vencimiento, no cuándo ocurrió. Esto lo
            // escribe el vigilante. `cumplido_at` lo escribe una persona.
            $tabla->timestamp('vencido_detectado_at')->nullable();
            $tabla->timestamp('cumplido_at')->nullable();
            $tabla->foreignUuid('cumplido_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            // Copia del texto de respaldo ya volcada en el expediente, con la
            // fecha en que se copió. Sirve para mostrar el original cuando la
            // causa se Archiva y el plazo ya no se edita.
            $tabla->text('respaldo_copiado_al_expediente')->nullable();
            $tabla->timestamp('respaldo_copiado_at')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'fecha_vencimiento']);
            $tabla->index(['tenant_id', 'estado']);
            $tabla->index(['tenant_id', 'expediente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plazos');
    }
};