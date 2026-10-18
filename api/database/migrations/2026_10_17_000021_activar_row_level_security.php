<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AISLAMIENTO ENTRE ESTUDIOS: Row Level Security (RLS) de PostgreSQL.
 *
 * Esta migración es el corazón del producto. Ver docs/03 para el análisis
 * completo de por qué se eligió RLS y no esquema por estudio.
 *
 * Es el mismo patrón ya decidido y documentado en
 * `apps/portal-autoservicio/docs/03-MULTITENANCY.md`, reutilizado tal cual:
 * no se inventó un cuarto enfoque.
 *
 * MECANISMO
 * ---------
 * 1. `public.app_tenant_id()` lee el valor de la variable de sesión
 *    `app.tenant_id`. Si no está definida, la función LANZA un error. No
 *    devuelve NULL ni una cadena vacía: falla de forma ruidosa.
 * 2. Cada tabla de negocio tiene una política que exige
 *    `tenant_id = app_tenant_id()`, tanto al leer (USING) como al escribir
 *    (WITH CHECK).
 * 3. `FORCE ROW LEVEL SECURITY` hace que la política se aplique también al
 *    dueño de la tabla. Sin FORCE, el dueño la saltaría.
 * 4. La variable se fija con `set_config('app.tenant_id', ..., true)`, es
 *    decir con alcance de TRANSACCIÓN. Al confirmar o revertir la
 *    transacción, PostgreSQL la descarta sola. Por eso no puede quedar colgada
 *    en una conexión reutilizada por el worker de colas, que es donde un
 *    `SET` normal filtraría datos de un estudio a otro.
 *
 * LAS TABLAS SIN RLS
 * ------------------
 *  - `estudios`: se lee antes del inicio de sesión, cuando todavía no hay
 *    contexto. Se compensa con la vista `vw_estudios_publicos`, que expone tres
 *    columnas y solo estudios activos.
 *  - `sessions`: es la sesión de Laravel, se consulta antes de saber quién es
 *    la persona. No tiene datos de negocio.
 *
 * REQUISITO DE DESPLIEGUE: el rol que usa la aplicación NO puede ser
 * superusuario ni tener BYPASSRLS. Con superusuario, PostgreSQL ignora las
 * políticas por completo. Lo comprueba:
 *   php artisan estudio:verificar-aislamiento
 */
return new class extends Migration
{
    /**
     * Tablas con datos de negocio, y por lo tanto con RLS.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'usuarios',
        'abogados',
        'configuracion_estudio',
        'materias',
        'tipos_documento',
        'estados_expediente',
        'clientes',
        'expedientes',
        'expediente_partes',
        'plazos',
        'documentos',
        'documento_versiones',
        'incidencias_documentales',
        'tareas',
        'bitacora_accesos',
    ];

    public function up(): void
    {
        // Se verifica ANTES de tocar nada. Si una tabla de la lista no existe
        // todavía o no tiene `tenant_id`, se falla acá y no se deja una base a
        // medias políticas.
        $this->verificarColumnaTenant();

        // 1. Función de contexto. SECURITY INVOKER (lo normal): no necesita
        //    privilegios propios.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.tenant_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'insufficient_privilege',
                        MESSAGE = 'No hay contexto de estudio en la conexión (app.tenant_id no está definida). Se aborta la operación para no exponer datos de otro estudio.',
                        HINT = 'La petición no pasó por App\Http\Middleware\ResolverTenant, o la operación se ejecutó fuera de una transacción.';
                END IF;

                RETURN valor::uuid;
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_tenant_id() IS
                'Devuelve el estudio de la petición. Lanza excepción si no hay contexto: el aislamiento falla cerrado.';
        SQL);

        // 2. Políticas y activación por tabla.
        foreach ($this->tablasProtegidas as $tabla) {
            DB::unprepared('ALTER TABLE public.' . $tabla . ' ENABLE ROW LEVEL SECURITY');

            // FORCE hace que la política aplique también al dueño. Es
            // indispensable: en desarrollo el dueño suele ser el mismo rol
            // que corre la aplicación.
            DB::unprepared('ALTER TABLE public.' . $tabla . ' FORCE ROW LEVEL SECURITY');

            $nombre = 'politica_aislamiento_' . $tabla;

            DB::unprepared(<<<SQL
                DROP POLICY IF EXISTS {$nombre} ON public.{$tabla};

                CREATE POLICY {$nombre} ON public.{$tabla}
                    FOR ALL
                    TO PUBLIC
                    USING (tenant_id = public.app_tenant_id())
                    WITH CHECK (tenant_id = public.app_tenant_id());
            SQL);
        }
    }

    public function down(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            DB::unprepared('DROP POLICY IF EXISTS politica_aislamiento_' . $tabla . ' ON public.' . $tabla);
            DB::unprepared('ALTER TABLE public.' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' DISABLE ROW LEVEL SECURITY');
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_tenant_id()');
    }

    /**
     * Falla ruidosamente si alguna tabla de la lista no existe o no tiene
     * `tenant_id`.
     *
     * Sin esto, un error de tipeo en la lista dejaría una tabla de datos de
     * clientes y causas sin política, y la migración pasaría igual: el
     * despliegue se creería protegido y no lo estaría.
     */
    private function verificarColumnaTenant(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = ''public'' AND table_name = ? AND column_name = ''tenant_id''
                 ) AS tiene_columna',
                [$tabla]
            );

            if ($existe === null || ! $existe->tiene_columna) {
                throw new RuntimeException(
                    'La tabla public.' . $tabla . ' no existe o no tiene columna tenant_id, y no puede protegerse con RLS. '
                    . 'Revise la migración antes de continuar.'
                );
            }
        }
    }
};