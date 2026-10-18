<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El texto de respaldo de un plazo es INMUTABLE, y la garantía está acá.
 *
 * POR QUÉ EN LA BASE Y NO EN EL CÓDIGO
 * -----------------------------------
 * El respaldo de un plazo es la razón por la que esa fecha se escribió. Si se
 * puede editar, entonces un plazo que se pasó "se corrige" cambiando su texto,
 * y el registro deja de demostrar nada. Es exactamente el dato que un estudio
 * necesita intacto.
 *
 * Una regla en un Policy o en un Request protege mientras nadie se salta la
 * aplicación. Una regla en la base protege siempre: también cuando alguien
 * entra por un cliente SQL, cuando un script de respaldo restaura y cuando un
 * error de aplicación intenta escribir.
 *
 * QUÉ SE DEJA PODER
 * -----------------
 *  - Insertar el respaldo (hay que poder crear el plazo).
 *  - La primera copia al expediente, al crearse el plazo.
 *  - Cambiar `estado` y las marcas de cumplimiento y de vencimiento, que son
 *    del vigilante y no del texto.
 *
 * QUÉ NO SE DEJA
 * --------------
 * Una vez insertada una fila, `texto_respaldo` no se puede volver a escribir.
 * Tampoco `fecha_vencimiento`: si la fecha se corrige, se ANULA el plazo y se
 * registra uno nuevo, con su propio respaldo. Queda el rastro de los dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.inmovilizar_respaldo_plazo()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.texto_respaldo IS DISTINCT FROM OLD.texto_respaldo THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'integrity_constraint_violation',
                        MESSAGE = 'El texto de respaldo de un plazo no se puede modificar. Si la fecha estaba mal, anule el plazo y registre uno nuevo.',
                        HINT = 'El respaldo de un plazo es la razón por la que se escribió esa fecha, y es la prueba que el estudio necesita intacta.';
                END IF;

                IF NEW.fecha_vencimiento IS DISTINCT FROM OLD.fecha_vencimiento THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'integrity_constraint_violation',
                        MESSAGE = 'La fecha de vencimiento de un plazo no se puede modificar. Anule el plazo y registre uno nuevo.',
                        HINT = 'Cambiar la fecha despues borraria la evidencia de que el plazo existio con otra fecha.';
                END IF;

                RETURN NEW;
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.inmovilizar_respaldo_plazo() IS
            'Impide cambiar el texto de respaldo y la fecha de un plazo ya registrado. La garantia vive en la base, no en la aplicacion.';
        SQL);

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS disparador_inmoviliza_respaldo ON public.plazos;

            CREATE TRIGGER disparador_inmoviliza_respaldo
                BEFORE UPDATE ON public.plazos
                FOR EACH ROW
                EXECUTE FUNCTION public.inmovilizar_respaldo_plazo();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS disparador_inmoviliza_respaldo ON public.plazos');
        DB::unprepared('DROP FUNCTION IF EXISTS public.inmovilizar_respaldo_plazo()');
    }
};