<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba que el aislamiento entre estudios esté bien puesto.
 *
 * POR QUÉ ESTE COMANDO EXISTE
 * ---------------------------
 * La Row Level Security de PostgreSQL deja de proteger en silencio en dos casos:
 *
 *  1. Si el rol de la aplicación es SUPERUSUARIO, PostgreSQL ignora las
 *     políticas por completo. Todo el aislamiento se evapora y nada falla.
 *
 *  2. Si el rol tiene BYPASSRLS, pasa lo mismo sin necesidad de ser
 *     superusuario.
 *
 * En los dos casos la aplicación arranca, responde y se ve bien, mientras una
 * configuración equivocada deja a un estudio viendo las causas de otro. Por eso esto se
 * corre en cada despliegue y falla ruidosamente.
 *
 * QUÉ COMPRUEBA
 * -------------
 *  1. Que el rol no sea superusuario ni tenga BYPASSRLS.
 *  2. Que cada tabla protegida tenga RLS activado y forzado.
 *  3. Que cada tabla tenga su política de aislamiento.
 *  4. Que la función de contexto siga fallando cerrada, es decir, que devuelva
 *     un ERROR y no cero filas cuando no hay contexto.
 */
class EstudioVerificarAislamientoCommand extends Command
{
    protected $signature = 'estudio:verificar-aislamiento';

    protected $description = 'Verifica que la Row Level Security aísle los estudios entre sí. Falla si no.';

    /** @var list<string> */
    private array $fallos = [];

    public function handle(): int
    {
        $this->info('Verificando el aislamiento entre estudios...');

        $this->revisarRol();
        $this->revisarPoliticas();
        $this->revisarFalloCerrado();

        if ($this->fallos !== []) {
            $this->error('');
            $this->error('EL AISLAMIENTO ENTRE ESTUDIOS NO ESTÁ BIEN PUESTO:');
            $this->error('');

            foreach ($this->fallos as $fallo) {
                $this->error('  - ' . $fallo);
            }

            $this->error('');
            $this->error('No dé por buena esta instalación hasta corregirlo. Con el aislamiento roto,');
            $this->error('un estudio puede ver los expedientes, clientes y documentos de otro.');

            return self::FAILURE;
        }

        $this->info('Aislamiento verificado. Cada estudio solo ve lo suyo.');

        return self::SUCCESS;
    }

    private function revisarRol(): void
    {
        // La columna del superusuario en `pg_roles` se llama `rolsuper`.
        // Antes se pedía `usesuper`, que no existe: la consulta reventaba y el
        // comando no verificaba nada. Un verificador que revienta no informa.
        try {
            $rol = DB::selectOne('SELECT rolname AS nombre, rolsuper AS superusuario, rolbypassrls AS bypass FROM pg_roles WHERE rolname = current_user');
        } catch (Throwable $e) {
            $this->fallos[] = 'No se pudo leer el rol de la base: ' . $e->getMessage();

            return;
        }

        if ($rol === null) {
            $this->fallos[] = 'No se pudo determinar el rol de la conexión.';

            return;
        }

        if ($rol->superusuario) {
            $this->fallos[] = $this->explicacionDelRolDefectuoso($rol->nombre, 'ES SUPERUSUARIO');
        }

        if ($rol->bypass) {
            $this->fallos[] = $this->explicacionDelRolDefectuoso($rol->nombre, 'tiene BYPASSRLS');
        }

        if (! $rol->superusuario && ! $rol->bypass) {
            $this->line('  rol de la base: sin superusuario y sin BYPASSRLS');
        }
    }

    /**
     * El texto que hay que leer cuando el rol conectado no es el correcto.
     *
     * Decir solamente "el rol es superusuario" no sirve de nada: quien lee esto
     * en la consola del despliegue tiene que entender por qué eso anula el
     * aislamiento y qué tiene que hacer al respecto, porque si no lo corrige y
     * vuelve a correr el comando, va a pasar lo mismo. Por eso el mensaje
     * explica las tres cosas: qué se detectó, por qué importa, y dónde está
     * el arreglo. La referencia del patrón está en
     * `apps/almacen/deploy/postgres/inicializar-rol.sh`.
     */
    private function explicacionDelRolDefectuoso(string $nombre, string $problema): string
    {
        return sprintf(
            'El rol "%s" %s. Un superusuario, o un rol con BYPASSRLS, ignora TODAS las políticas de '
            . 'Row Level Security. `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla, pero NO al '
            . 'superusuario ni a un rol con BYPASSRLS: con ese rol ninguna política de esta base se '
            . 'aplica y el aislamiento entre estudios no existe. Qué hacer: `POSTGRES_USER` debe ser '
            . 'el rol de ARRANQUE `estudio_abogados_admin`, que la aplicación nunca usa, y el rol de la '
            . 'aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en `deploy/postgres/'
            . 'inicializar-rol.sh`. Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh.',
            $nombre,
            $problema,
        );
    }

    private function revisarPoliticas(): void
    {
        $tablas = DB::select(
            "SELECT c.relname AS tabla, c.relrowsecurity AS rls, c.relforcerowsecurity AS forzado
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public' AND c.relkind = 'r'
               AND EXISTS (
                   SELECT 1 FROM pg_attribute a
                   WHERE a.attrelid = c.oid AND a.attname = 'tenant_id' AND a.attnum > 0
               )"
        );

        if ($tablas === []) {
            $this->fallos[] = 'No se encontró ninguna tabla con columna tenant_id.';

            return;
        }

        foreach ($tablas as $tabla) {
            if (! $tabla->rls) {
                $this->fallos[] = sprintf('La tabla public.%s tiene datos de tenants pero NO tiene Row Level Security.', $tabla->tabla);
            }

            if (! $tabla->forzado) {
                $this->fallos[] = sprintf(
                    'La tabla public.%s no tiene FORCE ROW LEVEL SECURITY. Sin FORCE, el dueño de la tabla '
                    . 'se salta la política, y en desarrollo el dueño suele ser el mismo rol de la aplicación.',
                    $tabla->tabla,
                );
            }

            $politica = DB::selectOne(
                'SELECT policyname AS nombre FROM pg_policies WHERE schemaname = \'public\' AND tablename = ?',
                [$tabla->tabla],
            );

            if ($politica === null) {
                $this->fallos[] = sprintf('La tabla public.%s no tiene ninguna política de aislamiento.', $tabla->tabla);
            }
        }

        if ($this->fallos === []) {
            $this->line(sprintf('  %d tablas protegidas, todas con RLS, FORCE y política', count($tablas)));
        }
    }

    /**
     * La función de contexto tiene que LANZAR excepción sin contexto, no
     * devolver cero filas en silencio.
     */
    private function revisarFalloCerrado(): void
    {
        try {
            DB::statement("SELECT set_config('app.tenant_id', '', false)");
        } catch (Throwable $e) {
            $this->fallos[] = 'No se pudo limpiar el contexto de estudio: ' . $e->getMessage();

            return;
        }

        try {
            DB::select('SELECT public.app_tenant_id()');

            $this->fallos[] = 'La función public.app_tenant_id() NO falla sin contexto. Si devolviera NULL o '
                . 'cadena vacía, una consulta sin estudio devolvería cero filas en silencio, y alguien podría '
                . 'corregirlo devolviendo NULL y terminar leyendo datos de otro estudio.';
        } catch (Throwable) {
            $this->line('  la función de contexto falla cerrada: correcto');
        }
    }
}
