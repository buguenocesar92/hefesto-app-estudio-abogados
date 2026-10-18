<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La vista que reemplaza a `estudios` antes del inicio de sesión.
 *
 * La tabla `estudios` NO lleva Row Level Security, a propósito: se consulta
 * durante el inicio de sesión, cuando todavía no hay un estudio en la sesión
 * que poner en la conexión (ver docs/03, "El punto ciego").
 *
 * Esa excepción se compensa acá. Antes de que la persona se identifique, la
 * aplicación NO lee la tabla cruda: lee esta vista, que expone SOLO tres
 * columnas y únicamente de los estudios activos. No expone correos, no expone
 * nombres de abogados, no expone nada de clientes o causas.
 *
 * Lo que sí se puede enumerar antes de autenticarse es el `slug`, porque es
 * público: aparece en la URL de entrada. Un atacante puede descubrir que el
 * Despacho Bravo usa esta aplicación. No puede ver más que el nombre. Está
 * anotado como riesgo residual asumido, no como algo resuelto.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DROP VIEW IF EXISTS public.vw_estudios_publicos;

            CREATE VIEW public.vw_estudios_publicos AS
            SELECT
                slug,
                nombre,
                activo
            FROM public.estudios
            WHERE activo = true;

            COMMENT ON VIEW public.vw_estudios_publicos IS
                'Unico punto de lectura de estudios antes del inicio de sesion. Expone tres columnas y solo estudios activos.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS public.vw_estudios_publicos');
    }
};