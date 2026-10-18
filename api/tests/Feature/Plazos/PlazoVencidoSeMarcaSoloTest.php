<?php

declare(strict_types=1);

namespace Tests\Feature\Plazos;

use App\Enums\EstadoPlazo;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Services\Plazos\VigilanteDePlazos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UN PLAZO VENCIDO SE MARCA SOLO.
 *
 * -------------------------------------------------------------------
 * ESTA PRUEBA VIGILA LA ADVERTENCIA QUE DEFINE EL PROYECTO
 * -------------------------------------------------------------------
 *
 * Que el plazo se marque solo NO significa que el sistema sepa si se cumplió.
 * Significa exactamente una cosa, que es la que se comprueba acá:
 *
 *   La fecha la escribió el abogado. El sistema compara esa fecha con la de
 *   hoy y marca la que ya pasó. NADA MÁS.
 *
 * Y la prueba inversa es la que importa: si el estudio NO configuró días de
 * aviso, el plazo NO se marca. Aunque su fecha ya haya pasado hace un año.
 *
 * El motivo está en `NivelPlazo`: un "rojo" con un número inventado le diría al
 * abogado que un plazo se venció cuando el sistema no tiene idea. Un tablero
 * con huecos se nota y se corrige. Un tablero con colores falsos se usa, y se
 * termina confiando en un número que salió de la nada.
 */
class PlazoVencidoSeMarcaSoloTest extends TestCase
{
    use RefreshDatabase;

    private function causa(array $estudio): Expediente
    {
        return Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
        ]);
    }

    public function test_el_vigilante_marca_el_plazo_cuya_fecha_ya_paso(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        // La fecha la escribió el abogado. El sistema no la dedujo.
        $plazo = Plazo::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
            'fecha_vencimiento' => now()->subDays(5)->format('Y-m-d'),
            'estado' => EstadoPlazo::Pendiente->value,
            'dias_alerta' => 7,
            'dias_alerta_critica' => 2,
        ]);

        $this->assertSame(EstadoPlazo::Pendiente, $plazo->estado);

        $vigilante = new VigilanteDePlazos(app(\App\Services\Tenancy\ContextoTenant::class));

        $resultado = $vigilante->marcarVencidos($estudio['estudio']->id);

        $this->assertSame(1, $resultado['marcados']);

        $plazo->refresh();

        $this->assertSame(EstadoPlazo::Vencido, $plazo->estado);
        $this->assertNotNull($plazo->vencido_detectado_at);
    }

    public function test_no_marca_un_plazo_que_aun_no_llega(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        $plazo = Plazo::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
            'fecha_vencimiento' => now()->addDays(30)->format('Y-m-d'),
            'estado' => EstadoPlazo::Pendiente->value,
            'dias_alerta' => 7,
        ]);

        (new VigilanteDePlazos(app(\App\Services\Tenancy\ContextoTenant::class)))
            ->marcarVencidos($estudio['estudio']->id);

        $this->assertSame(EstadoPlazo::Pendiente, $plazo->fresh()->estado);
    }

    public function test_no_marca_un_plazo_cumplido_todavia_vencido(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        $plazo = Plazo::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
            'fecha_vencimiento' => now()->subDays(2)->format('Y-m-d'),
            'estado' => EstadoPlazo::Cumplido->value,
            'dias_alerta' => 7,
        ]);

        $resultado = (new VigilanteDePlazos(app(\App\Services\Tenancy\ContextoTenant::class)))
            ->marcarVencidos($estudio['estudio']->id);

        // Un plazo se puede cumplir con dos días de atraso, y eso es
        // información que el estudio necesita, no un error que haya que esconder.
        $this->assertSame(0, $resultado['marcados']);
        $this->assertSame(EstadoPlazo::Cumplido, $plazo->fresh()->estado);
    }

    /**
     * LA PRUEBA INVERSA, Y LA MÁS IMPORTANTE DEL ARCHIVO.
     *
     * Sin días de aviso configurados, el plazo NO se marca. Ni aunque la fecha
     * haya pasado hace un año.
     */
    public function test_sin_dias_de_alerta_no_marca_nada_aunque_la_fecha_haya_pasado(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        $plazo = Plazo::factory()->sinAlerta()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
            'fecha_vencimiento' => now()->subYear()->format('Y-m-d'),
            'estado' => EstadoPlazo::Pendiente->value,
        ]);

        $resultado = (new VigilanteDePlazos(app(\App\Services\Tenancy\ContextoTenant::class)))
            ->marcarVencidos($estudio['estudio']->id);

        $this->assertSame(0, $resultado['marcados']);
        $this->assertSame(1, $resultado['sin_alerta']);

        // Sigue pendiente, y el sistema no finge que sepa algo.
        $this->assertSame(EstadoPlazo::Pendiente, $plazo->fresh()->estado);

        $nivel = $plazo->fresh()->nivel();

        $this->assertSame('sin_alerta', $nivel->nivel->value);
        $this->assertFalse($nivel->marcado());
        $this->assertStringContainsString('no configuró', $nivel->resumen());
    }

    public function test_la_marca_de_vencimiento_no_se_pisa_al_correr_de_nuevo(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        $plazo = Plazo::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
            'fecha_vencimiento' => now()->subDays(1)->format('Y-m-d'),
            'dias_alerta' => 7,
        ]);

        $vigilante = new VigilanteDePlazos(app(\App\Services\Tenancy\ContextoTenant::class));

        $vigilante->marcarVencidos($estudio['estudio']->id);
        $primeraMarca = $plazo->fresh()->vencido_detectado_at;

        $vigilante->marcarVencidos($estudio['estudio']->id);

        // La segunda corrida no cambia nada y no vuelve a marcar. La fecha en
        // que el estudio se enteró no depende de cuántas veces se corrió el
        // vigilante.
        $this->assertEquals($primeraMarca, $plazo->fresh()->vencido_detectado_at);
    }

    /**
     * El texto de respaldo NO se puede modificar.
     *
     * Si se pudiera, un plazo que se pasó "se corrige" cambiando su texto, y el
     * registro deja de demostrar nada.
     */
    public function test_el_texto_de_respaldo_no_se_puede_modificar(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        $plazo = Plazo::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
            'texto_respaldo' => 'Plazo contado desde la notificación, según resolución del 2026-03-30.',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('plazos')
            ->where('id', $plazo->id)
            ->update(['texto_respaldo' => 'Texto inventado después']);
    }

    /**
     * La fecha tampoco. Si estaba mal, se anula el plazo y se registra otro.
     */
    public function test_la_fecha_de_vencimiento_no_se_puede_modificar(): void
    {
        $estudio = $this->crearEstudio();
        $causa = $this->causa($estudio);

        $plazo = Plazo::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'expediente_id' => $causa->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('plazos')
            ->where('id', $plazo->id)
            ->update(['fecha_vencimiento' => now()->addYear()->format('Y-m-d')]);
    }
}