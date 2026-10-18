<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios: las personas que entran al sistema.
 *
 * La identidad la trae Authentik (correo, nombre). Acá se guarda SOLO lo que
 * el estudio necesita saber y Authentik no sabe: a que estudio pertenece la
 * persona y que rol tiene adentro.
 *
 * `subject` es el identificador estable que entrega Authentik en el token
 * (`sub`). No es el correo: el correo puede cambiar y la cuenta no.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            $tabla->string('subject', 191);
            $tabla->string('correo', 191);
            $tabla->string('nombre', 160)->nullable();

            // Ver App\Enums\RolUsuario.
            $tabla->string('rol', 24)->default('abogado');

            $tabla->timestamp('ultimo_acceso')->nullable();
            $tabla->timestampsTz();

            // El subject identifica a la persona DENTRO de un estudio. La
            // combinacion tiene que ser unica, o el inicio de sesion no
            // sabria a que cuenta attacharse.
            $tabla->unique(['tenant_id', 'subject']);

            // El correo es unico DENTRO del estudio, no en toda la base: dos
            // estudios distintos pueden tener a la misma persona.
            $tabla->unique(['tenant_id', 'correo']);

            $tabla->index(['tenant_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};