<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Expediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Un abogado NO ve la cartera de otro abogado.
 *
 * -------------------------------------------------------------------
 * ESTA ES LA PRUEBA MÁS IMPORTANTE DEL MÓDULO DE AISLAMIENTOS
 * -------------------------------------------------------------------
 *
 * El aislamiento entre estudios lo garantiza la Row Level Security, y es
 * sólido: un estudio no ve nada de otro estudio, pase lo que pase en el código.
 *
 * Esta prueba es del OTRO lado, y es más delicate. Dentro de un mismo estudio
 * hay varios abogados, y cada uno lleva su propia cartera. La RLS no ayuda acá:
 * las dos carteras son del mismo tenant y la política las deja pasar.
 *
 * Lo que filtra es la `ExpedientePolicy`. Y hay una razón de fondo: en un
 * estudio chico todos se saben, pero eso no significa que todos deban ver todo.
 * Una pasante que puede abrir la cartera completa ve causas en las que no
 * participa, incluyendo las de clientes ajenos a su trabajo.
 *
 * El filtro va en la CONSULTA, no en la vista. Filtrar después de traer
 * significa haber recibido los datos de la cartera ajena en memoria.
 */
class CarteraDelAbogadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_abogado_solo_ve_las_causas_de_su_cartera(): void
    {
        $estudio = $this->crearEstudio();

        $causaPropia = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
            'titulo' => 'Causa del abogado',
        ]);

        $causaDeOtro = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaSocio']->id,
            'titulo' => 'Causa del socio',
        ]);

        $this->comoUsuario($estudio['abogado']);

        $vistas = Expediente::query()->pluck('id');

        $this->assertTrue($vistas->contains($causaPropia->id));
        $this->assertFalse($vistas->contains($causaDeOtro->id), 'El abogado vio una causa que no es de su cartera.');
    }

    public function test_un_abogado_no_puede_abrir_una_causa_de_otro_abogado(): void
    {
        $estudio = $this->crearEstudio();

        $causaDeOtro = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaSocio']->id,
        ]);

        $this->comoUsuario($estudio['abogado']);

        $this->assertFalse(
            $this->user->can('view', $causaDeOtro),
            'Un abogado no debería poder ver una causa de la cartera de otro.',
        );
    }

    public function test_el_socio_ve_toda_la_cartera_del_estudio(): void
    {
        $estudio = $this->crearEstudio();

        $causaDelAbogado = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
        ]);

        $this->comoUsuario($estudio['socio']);

        $this->assertTrue($this->user->can('view', $causaDelAbogado));
        $this->assertSame(1, Expediente::query()->count());
    }

    public function test_el_administrador_ve_toda_la_cartera_del_estudio(): void
    {
        $estudio = $this->crearEstudio();

        $causaDelAbogado = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
        ]);

        $this->comoUsuario($estudio['administrador']);

        $this->assertTrue($this->user->can('view', $causaDelAbogado));
    }

    public function test_una_cuenta_sin_ficha_de_abogado_no_ve_nada(): void
    {
        $estudio = $this->crearEstudio();

        $causa = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
        ]);

        // Una cuenta con rol de abogado pero SIN ficha de abogado asociada. Es el
        // caso raro, y dejarlo pasar sería abrir la cartera a cualquiera con
        // una cuenta válida.
        $usuarioHuerfano = \App\Models\Usuario::factory()->abogado()->de($estudio['estudio'])->create([
            'correo' => 'huerfano@' . $estudio['estudio']->slug . '.cl',
        ]);

        $this->comoUsuario($usuarioHuerfano);

        $this->assertFalse($this->user->can('view', $causa));
        $this->assertSame(0, Expediente::query()->count());
    }

    public function test_el_pasante_ve_su_cartera_pero_no_abre_causas(): void
    {
        $estudio = $this->crearEstudio();

        $causaPropia = Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
        ]);

        $this->comoUsuario($estudio['pasante']);

        $this->assertFalse(
            $this->user->can('create', Expediente::class),
            'Un pasante no debería poder abrir expedientes.',
        );
        $this->assertFalse($this->user->can('view', $causaPropia));
    }
}