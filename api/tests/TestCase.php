<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Abogado;
use App\Models\Cliente;
use App\Models\ConfiguracionEstudio;
use App\Models\EstadoExpediente;
use App\Models\Estudio;
use App\Models\Materia;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pruebas.
 *
 * Aporta utilidades para el aislamiento, que es lo que más se prueba.
 *
 * POR QUÉ `fijarTenant` USA ALCANCE DE SESIÓN Y NO DE TRANSACCIÓN
 * ---------------------------------------------------------------
 * En la aplicación real es al revés: `set_config(..., true)`, alcance de
 * transacción, para que el worker de colas no arrastre el estudio de otro.
 *
 * En las pruebas cada caso abre su propia aplicación y su propia conexión, así
 * que el valor no sobrevive entre pruebas. El alcance de sesión es seguro acá y
 * hace falta: las aserciones corren FUERA de la transacción del middleware, y
 * con alcance de transacción no verían el mismo contexto.
 *
 * La garantía de alcance de transición se prueba aparte, en
 * `test_el_contexto_de_estudio_no_sobrevive_a_la_transaccion`.
 */
abstract class TestCase extends BaseTestCase
{
    /** Estudio fijado para la prueba en curso. */
    protected ?string $tenantDePrueba = null;

    /**
     * Autentica a una persona y fija su contexto de estudio en la base.
     *
     * IMPORTANTE: autenticar con `actingAs()` NO fija el contexto en la base de
     * datos, porque el middleware `ResolverTenant` corre dentro del grupo
     * `web`, que las pruebas de API no ejecutan. Si solo se autentica, la RLS no
     * tendría contra qué filtrar.
     */
    protected function comoUsuario(Usuario $usuario): static
    {
        $this->actingAs($usuario);

        return $this->fijarTenant($usuario->tenant_id);
    }

    /**
     * Emula lo que hace `App\Http\Middleware\ResolverTenant`.
     */
    protected function fijarTenant(?string $tenantId): static
    {
        if ($tenantId === null) {
            return $this;
        }

        DB::select('SELECT set_config(?, ?, false)', [ContextoTenant::VARIABLE, $tenantId]);

        $this->tenantDePrueba = $tenantId;

        return $this;
    }

    /**
     * Crea un estudio con su administrador, dos abogados y catálogo.
     *
     * @return array{estudio: Estudio, administrador: Usuario, socio: Usuario,
     *               abogado: Usuario, pasante: Usuario,
     *               fichaSocio: Abogado, fichaAbogado: Abogado,
     *               materia: Materia, tipo: TipoDocumento,
     *               estado: EstadoExpediente, cliente: Cliente}
     */
    protected function crearEstudio(string $dominio = 'estudio-ejemplo.cl'): array
    {
        $estudio = Estudio::factory()->create([
            'slug' => 'estudio-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)),
            'nombre' => 'Estudio de prueba',
        ]);

        $this->fijarTenant($estudio->id);

        $administrador = Usuario::factory()->administrador()->de($estudio)->create([
            'correo' => 'admin@' . $dominio,
        ]);

        $socio = Usuario::factory()->socio()->de($estudio)->create([
            'correo' => 'socio@' . $dominio,
        ]);

        $abogado = Usuario::factory()->abogado()->de($estudio)->create([
            'correo' => 'abogado@' . $dominio,
        ]);

        $pasante = Usuario::factory()->pasante()->de($estudio)->create([
            'correo' => 'pasante@' . $dominio,
        ]);

        $fichaSocio = Abogado::factory()->create([
            'tenant_id' => $estudio->id,
            'usuario_id' => $socio->id,
            'nombre' => 'Socio',
            'apellidos' => 'De Prueba',
        ]);

        $fichaAbogado = Abogado::factory()->create([
            'tenant_id' => $estudio->id,
            'usuario_id' => $abogado->id,
            'nombre' => 'Abogado',
            'apellidos' => 'De Prueba',
        ]);

        $materia = Materia::factory()->create([
            'tenant_id' => $estudio->id,
            'codigo' => 'PRUEBA',
            'nombre' => 'Materia de prueba',
        ]);

        $tipo = TipoDocumento::factory()->create([
            'tenant_id' => $estudio->id,
            'codigo' => 'PRUEBA',
            'nombre' => 'Tipo de prueba',
        ]);

        $estado = EstadoExpediente::factory()->create([
            'tenant_id' => $estudio->id,
            'codigo' => 'INGRESO',
            'nombre' => 'Ingresada',
        ]);

        $cliente = Cliente::factory()->create([
            'tenant_id' => $estudio->id,
        ]);

        ConfiguracionEstudio::query()->create([
            'tenant_id' => $estudio->id,
            'dias_alerta_plazo' => 7,
            'dias_alerta_critica' => 2,
        ]);

        return [
            'estudio' => $estudio,
            'administrador' => $administrador,
            'socio' => $socio,
            'abogado' => $abogado,
            'pasante' => $pasante,
            'fichaSocio' => $fichaSocio,
            'fichaAbogado' => $fichaAbogado,
            'materia' => $materia,
            'tipo' => $tipo,
            'estado' => $estado,
            'cliente' => $cliente,
        ];
    }

    /**
     * Crea un SEGUNDO estudio, independiente del primero, y deja su contexto
     * fijado.
     *
     * @return array<string, mixed>
     */
    protected function crearSegundoEstudio(string $dominio = 'otro-estudio.cl'): array
    {
        return $this->crearEstudio($dominio);
    }
}