<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora de accesos solo admite filas nuevas.
 *
 * Una auditoría que se puede editar no es una auditoría. Un atacante con
 * acceso a la base, o un error de aplicación con permisos de más, podría
 * borrar las huellas; y en un estudio de abogados la pregunta "¿quién miró
 * este expediente?" tiene que tener respuesta confiable.
 *
 * El disparador está en la base, no en el código, por el mismo motivo que el
 * del respaldo de los plazos: una garantía que depende de que nadie se equivoque
 * no es una garantía.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.bloquear_borrado_bitacora()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION USING
                    ERRCODE = 'integrity_constraint_violation',
                    MESSAGE = 'La bitacora de accesos no admite UPDATE ni DELETE.',
                    HINT = 'La bitacora solo admite inserciones. Si una fila esta mal, se agrega otra que la corrija.';
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.bloquear_borrado_bitacora() IS
            'Impide modificar o borrar filas de la bitacora de accesos. Una auditoria editable no es una auditoria.';
        SQL);

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS disparador_solo_agrega_bitacora ON public.bitacora_accesos;

            CREATE TRIGGER disparador_solo_agrega_bitacora
                BEFORE UPDATE OR DELETE ON public.bitacora_accesos
                FOR EACH ROW
                EXECUTE FUNCTION public.bloquear_borrado_bitacora();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS disparador_solo_agrega_bitacora ON public.bitacora_accesos');
        DB::unprepared('DROP FUNCTION IF EXISTS public.bloquear_borrado_bitacora()');
    }
};