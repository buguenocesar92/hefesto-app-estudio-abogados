<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes: las personas que contratan al estudio.
 *
 * DATO PERSONAL PROTEGIDO POR LA LEY 19.628.
 *
 * El nombre de un cliente en un estudio de abogados no es un dato de contacto:
 * es la lista de a quiénes representa este despacho. Se guarda lo justo:
 * nombre, documento de identidad (si el estudio lo necesita) y cómo
 * contactarlo. El sistema NO guarda dirección, teléfono de un tercero, ni
 * antecedentes económicos: si el estudio necesita algo más, lo deja en el
 * expediente físico y lo anota acá como texto libre.
 *
 * Un cliente puede tener muchas causas y, en cada una, tener roles distintos:
 * ver `expediente_partes`. El rol NO es un atributo de la persona.
 *
 * AISLAMIENTO: RLS por `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('estudios')->cascadeOnDelete();

            // "Persona natural" o "Persona jurídica". No hay más tipos, y
            // agregarlos no aporta nada: el sistema no trata distinto a una
            // sociedad que a una persona, solo guarda el nombre.
            $tabla->string('tipo', 24)->default('persona_natural');

            $tabla->string('nombre', 160);
            $tabla->string('documento_identidad', 32)->nullable();
            $tabla->string('correo', 191)->nullable();
            $tabla->string('telefono', 40)->nullable();

            $tabla->text('notas')->nullable();
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'documento_identidad']);
            $tabla->index(['tenant_id', 'nombre']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};