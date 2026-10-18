<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente y partes: el rol es de la RELACIÓN, no de la persona.
 *
 * Esta es una de las decisiones de diseño más importantes del modelo, y no es
 * un detalle. Una misma persona puede ser demandada en una causa y ser el
 * cliente del estudio en otra. Si el rol fuera una columna de `clientes`,
 * sería imposible representar eso: habría que duplicar a la persona con dos
 * filas y resolver a mano cuál es cuál, o elegir un rol y perder el otro.
 *
 * Por eso el rol vive acá, en la tabla que une expediente y parte, y no en la
 * persona. La misma fila de `clientes` participa en diez expedientes con diez
 * roles distintos.
 *
 * `es_externo` marca si la parte es una persona que NO es cliente del
 * estudio. Se marca aparte porque sus datos están protegidos con más
 * cuidado todavía: la contraparte de una causa jamás pasó por este despacho.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_partes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();
            $tabla->foreignUuid('expediente_id')->constrained('expedientes')->cascadeOnDelete();

            // La parte es siempre un cliente del estudio: si no lo es, primero
            // se registra como cliente y después se vincula. Así el estudio
            // tiene un padrón único de personas y no dos listas.
            $tabla->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();

            // Ver App\Enums\RolParte: demandado, demandante, tercero,
            // testigo. El sistema NO sabe qué implican esos roles en derecho:
            // sólo los muestra y los filtra.
            $tabla->string('rol', 32);

            // Persona ajena al estudio (la contraparte, un tercero). Se marca
            // aparte porque es el caso de mayor sensibilidad.
            $tabla->boolean('es_externo')->default(false);

            $tabla->string('notas')->nullable();
            $tabla->timestampsTz();

            // Una persona tiene UN rol por expediente. Si hiciera falta más de
            // uno, se documenta en `notas`: no se modela a propósito.
            $tabla->unique(['expediente_id', 'cliente_id', 'rol']);

            $tabla->index(['tenant_id', 'cliente_id']);
            $tabla->index(['tenant_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_partes');
    }
};
