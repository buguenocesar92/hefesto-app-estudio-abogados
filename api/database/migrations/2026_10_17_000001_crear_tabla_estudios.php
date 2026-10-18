<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estudios de abogados: el TENANT.
 *
 * Un estudio es un estudio juridico. Todo lo demas cuelga de el. Varias
 * instalaciones de esta aplicacion (o el mismo despliegue para varios
 * estudios) comparten una base de datos, y esta tabla es la que los separa.
 *
 * AISLAMIENTO
 * -----------
 * Esta tabla NO lleva Row Level Security, a proposito: se consulta durante el
 * inicio de sesion, cuando todavia no hay contexto de estudio en la conexion
 * (ver docs/03, seccion "El punto ciego").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->string('slug', 60)->unique();
            $tabla->string('nombre', 160);

            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            // Un estudio sin nombre no sirve para nada, y un estudio sin slug
            // no se puede encuadrar en el inicio de sesion.
            $tabla->index(['activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudios');
    }
};