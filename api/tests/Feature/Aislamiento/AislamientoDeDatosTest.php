<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Documento;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\Plazo;
use App\Models\Cliente;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRUEBAS CRÍTICAS: un estudio no puede leer los datos de otro.
 *
 * En un estudio de abogados esto no es un problema comercial: son los nombres,
 * las causas y los documentos de personas identificables, protegidos por la
 * Ley 19.628.
 *
 * Estas pruebas van contra PostgreSQL de verdad, porque el aislamiento está
 * implementado con Row Level Security. Si se corrieran contra SQLite, pasarían
 * sin comprobar nada. Ver phpunit.xml.
 *
 * Lo que se comprueba acá:
 *
 *  1. Sin contexto de estudio, las tablas devuelven un ERROR, no filas.
 *  2. Con el contexto de otro estudio, una consulta devuelve SOLO sus filas.
 *  3. Un `id` de otro estudio no existe para esta persona.
 *  4. Un expediente NO ve los documentos de otro expediente.
 *  5. No se puede escribir una fila en nombre de otro estudio.
 *  6. El contexto de estudio no sobrevive a la transacción.
 */
class AislamientoDeDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_contexto_de_estudio_la_consulta_falla_en_vez_de_traer_datos(): void
    {
        $primero = $this->crearEstudio();
        Expediente::factory()->create([
            'tenant_id' => $primero['estudio']->id,
            'materia_id' => $primero['materia']->id,
        ]);

        // Se limpia el contexto a propósito: es la situación de una consulta que
        // se escapa del middleware.
        //
        // Lo que se espera NO es "cero filas", sino un ERROR. `app_tenant_id()`
        // lanza excepción cuando no hay contexto y la política RLS aborta. Cero
        // filas sería más cómodo de probar, pero dejaría pasar el caso
        // peligroso: que alguien lo "arregle" devolviendo NULL y termine
        // leyendo filas del estudio equivocado sin que nada lo delate.
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(QueryException::class);

        Expediente::query()->count();
    }

    public function test_cada_estudio_solo_ve_sus_propios_expedientes(): void
    {
        $primero = $this->crearEstudio();
        Expediente::factory()->count(3)->create([
            'tenant_id' => $primero['estudio']->id,
            'materia_id' => $primero['materia']->id,
        ]);

        $segundo = $this->crearSegundoEstudio();
        Expediente::factory()->count(2)->create([
            'tenant_id' => $segundo['estudio']->id,
            'materia_id' => $segundo['materia']->id,
        ]);

        $this->fijarTenant($primero['estudio']->id);
        $this->assertSame(3, Expediente::query()->count());
        $this->assertSame($primero['estudio']->id, Expediente::query()->value('tenant_id'));

        $this->fijarTenant($segundo['estudio']->id);
        $this->assertSame(2, Expediente::query()->count());
        $this->assertSame($segundo['estudio']->id, Expediente::query()->value('tenant_id'));
    }

    public function test_el_expediente_de_otro_estudio_no_se_puede_leer_ni_escribir(): void
    {
        $primero = $this->crearEstudio();
        $segundo = $this->crearSegundoEstudio();

        $expedienteAjeno = Expediente::factory()->create([
            'tenant_id' => $segundo['estudio']->id,
            'materia_id' => $segundo['materia']->id,
        ]);

        $this->fijarTenant($primero['estudio']->id);

        $this->assertNull(Expediente::find($expedienteAjeno->id));

        // Y tampoco se puede tocar: la política tiene WITH CHECK.
        $this->expectException(QueryException::class);

        DB::table('expedientes')
            ->where('id', $expedienteAjeno->id)
            ->update(['titulo' => 'secuestrado']);
    }

    /**
     * EL CASO QUE DA NIESTO A ESTE PRODUCTO.
     *
     * Un expediente del estudio A no puede ver los documentos de un expediente
     * del estudio B. Si esto fallara, un estudio vería los documentos de otro:
     * contratos, demandas y poder de una causa ajena.
     */
    public function test_un_expediente_no_ve_los_documentos_de_otro_estudio(): void
    {
        $primero = $this->crearEstudio();
        $expedientePropio = Expediente::factory()->create([
            'tenant_id' => $primero['estudio']->id,
            'materia_id' => $primero['materia']->id,
        ]);
        Documento::factory()->count(2)->create([
            'tenant_id' => $primero['estudio']->id,
            'expediente_id' => $expedientePropio->id,
        ]);

        $segundo = $this->crearSegundoEstudio();
        $expedienteAjeno = Expediente::factory()->create([
            'tenant_id' => $segundo['estudio']->id,
            'materia_id' => $segundo['materia']->id,
        ]);
        $documentoAjeno = Documento::factory()->create([
            'tenant_id' => $segundo['estudio']->id,
            'expediente_id' => $expedienteAjeno->id,
        ]);

        $this->fijarTenant($primero['estudio']->id);

        $this->assertNull(Documento::find($documentoAjeno->id));

        // Y el expediente propio solo muestra SUS documentos: la relación de
        // Eloquent también pasa por la política.
        $visibles = $expedientePropio->documentos()->pluck('id');

        $this->assertCount(2, $visibles);
        $this->assertFalse($visibles->contains($documentoAjeno->id));
    }

    public function test_no_se_puede_insertar_una_fila_con_el_estudio_de_otro(): void
    {
        $primero = $this->crearEstudio();
        $segundo = $this->crearSegundoEstudio();

        // Contexto del primer estudio, pero se intenta guardar un expediente
        // del segundo. La política WITH CHECK lo impide.
        $this->fijarTenant($primero['estudio']->id);

        $this->expectException(QueryException::class);

        Expediente::query()->create([
            'tenant_id' => $segundo['estudio']->id,
            'folio' => 'EXP-AJENO',
            'materia_id' => $primero['materia']->id,
            'fecha_inicio' => now()->toDateString(),
            'titulo' => 'No debería existir',
        ]);
    }

    public function test_los_clientes_de_otro_estudio_no_se_ven(): void
    {
        $primero = $this->crearEstudio();
        Cliente::factory()->count(2)->create(['tenant_id' => $primero['estudio']->id]);

        $segundo = $this->crearSegundoEstudio();
        Cliente::factory()->count(3)->create(['tenant_id' => $segundo['estudio']->id]);

        $this->fijarTenant($primero['estudio']->id);
        $this->assertSame(2, Cliente::query()->count());

        $this->fijarTenant($segundo['estudio']->id);
        $this->assertSame(3, Cliente::query()->count());
    }

    public function test_los_plazos_de_otro_estudio_no_se_ven(): void
    {
        $primero = $this->crearEstudio();
        $expediente = Expediente::factory()->create([
            'tenant_id' => $primero['estudio']->id,
            'materia_id' => $primero['materia']->id,
        ]);
        Plazo::factory()->count(3)->create([
            'tenant_id' => $primero['estudio']->id,
            'expediente_id' => $expediente->id,
        ]);

        $segundo = $this->crearSegundoEstudio();
        $expedienteAjeno = Expediente::factory()->create([
            'tenant_id' => $segundo['estudio']->id,
            'materia_id' => $segundo['materia']->id,
        ]);
        Plazo::factory()->count(4)->create([
            'tenant_id' => $segundo['estudio']->id,
            'expediente_id' => $expedienteAjeno->id,
        ]);

        $this->fijarTenant($primero['estudio']->id);
        $this->assertSame(3, Plazo::query()->count());

        $this->fijarTenant($segundo['estudio']->id);
        $this->assertSame(4, Plazo::query()->count());
    }

    public function test_las_partes_de_otro_estudio_no_se_ven(): void
    {
        $primero = $this->crearEstudio();
        $expediente = Expediente::factory()->create([
            'tenant_id' => $primero['estudio']->id,
            'materia_id' => $primero['materia']->id,
        ]);
        ExpedienteParte::factory()->count(2)->create([
            'tenant_id' => $primero['estudio']->id,
            'expediente_id' => $expediente->id,
            'cliente_id' => $primero['cliente']->id,
        ]);

        $this->fijarTenant('11111111-1111-4111-8111-111111111111');

        $this->assertSame(0, ExpedienteParte::query()->count());
    }

    public function test_el_contexto_de_estudio_no_sobrevive_a_la_transaccion(): void
    {
        $estudio = $this->crearEstudio();
        Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
        ]);

        $contexto = new ContextoTenant();

        $conteo = $contexto->ejecutarAislado(
            $estudio['estudio']->id,
            fn (): int => Expediente::query()->count(),
        );

        $this->assertSame(1, $conteo);

        $this->assertNull(
            $contexto->tenantIdEnConexion(),
            'La variable de contexto debe desaparecer al terminar la transacción. '
            . 'Si queda, el worker de colas podría leer datos de otro estudio.',
        );
    }

    public function test_la_funcion_de_contexto_falla_cerrada_sin_estudio(): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, '']);

        $this->expectException(QueryException::class);

        DB::select('SELECT public.app_tenant_id()');
    }
}