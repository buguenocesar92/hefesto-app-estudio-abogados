<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoPlazo;
use App\Enums\RolParte;
use App\Models\Abogado;
use App\Models\Cliente;
use App\Models\ConfiguracionEstudio;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\EstadoExpediente;
use App\Models\Expediente;
use App\Models\ExpedienteParte;
use App\Models\Materia;
use App\Models\Plazo;
use App\Models\TipoDocumento;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Un estudio de ejemplo, con expedientes, plazos, documentos y horas.
 *
 * -------------------------------------------------------------------
 * LO QUE ESTA SEMILLA NO HACE
 * -------------------------------------------------------------------
 * No pone ningún plazo de ejemplo inventado. Los plazos que crea llevan la
 * fecha que el SEED declara explícitamente, y un texto de respaldo que
 * corresponde a esa fecha.
 *
 * El sistema no dice "este plazo vence en 30 días". La semilla escribe
 * "vence el 2026-11-14" y "por escrito, según lo resuelto por el tribunal el
 * 2026-09-30". El número 30 no aparece en ningún lado como una regla: aparece
 * solo dentro del texto, que es la razón que un abogado escribió.
 *
 * -------------------------------------------------------------------
 * EL CONTEXTO DE ESTUDIO
 * -------------------------------------------------------------------
 * Todo lo que no sea la tabla `estudios` necesita el contexto fijado, o la RLS
 * rechaza la escritura. Por eso esta semilla usa `ContextoTenant`, igual que lo
 * haría la aplicación.
 */
class EstudioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $estudio = Estudio::query()->firstOrCreate(
            ['slug' => 'estudio-demo'],
            ['nombre' => 'Estudio Jurídico Demo', 'activo' => true],
        );

        $contexto = new ContextoTenant();

        $contexto->ejecutarAislado($estudio->id, function () use ($estudio): void {
            $configuracion = $this->configuracion($estudio->id);
            $materias = $this->materias($estudio->id);
            $tipos = $this->tiposDocumento($estudio->id);
            $estados = $this->estados($estudio->id);
            $personas = $this->personas($estudio->id);

            $expedientes = $this->expedientes(
                $estudio->id,
                $materias,
                $estados,
                $personas,
            );

            $this->plazos($estudio->id, $expedientes, $configuracion);
            $this->documentos($estudio->id, $expedientes, $tipos);
            $this->horas($estudio->id, $expedientes, $personas['abogados']);
        });
    }

    private function configuracion(string $tenantId): ConfiguracionEstudio
    {
        $configuracion = ConfiguracionEstudio::query()->firstOrNew(['tenant_id' => $tenantId]);

        $configuracion->tenant_id = $tenantId;
        $configuracion->zona_horaria = 'America/Santiago';
        $configuracion->moneda = 'CLP';
        $configuracion->valor_hora_imputable = 95000;

        // DÍAS DE AVISO. Son una alarma interna del estudio, elegidos por el
        // socio. NO son plazos legales y no se usan para calcular nada.
        $configuracion->dias_alerta_plazo = 7;
        $configuracion->dias_alerta_critica = 2;
        $configuracion->avisar_documento_duplicado = true;
        $configuracion->save();

        return $configuracion;
    }

    /**
     * @return array<string, Materia>
     */
    private function materias(string $tenantId): array
    {
        $datos = [
            ['MAT-CIV', 'Civil'],
            ['MAT-LAB', 'Laboral'],
            ['MAT-FAM', 'Familia y Sucesiones'],
            ['MAT-PEN', 'Penal'],
        ];

        $materias = [];

        foreach ($datos as [$codigo, $nombre]) {
            $materias[$codigo] = Materia::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true],
            );
        }

        return $materias;
    }

    /**
     * @return array<string, TipoDocumento>
     */
    private function tiposDocumento(string $tenantId): array
    {
        $datos = [
            ['TIP-CON', 'Contrato', true],
            ['TIP-POD', 'Poder', true],
            ['TIP-SEN', 'Sentencia', true],
            ['TIP-CAR', 'Carta', false],
        ];

        $tipos = [];

        foreach ($datos as [$codigo, $nombre, $exigeVersion]) {
            $tipos[$codigo] = TipoDocumento::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'codigo' => $codigo],
                ['nombre' => $nombre, 'exige_version' => $exigeVersion, 'activo' => true],
            );
        }

        return $tipos;
    }

    /**
     * @return array<string, EstadoExpediente>
     */
    private function estados(string $tenantId): array
    {
        $datos = [
            ['INGRESO', 'Ingresada', false, 1],
            ['REVISION', 'En revisión', false, 2],
            ['TRAMITACION', 'En tramitación', false, 3],
            ['SUSPENDIDO', 'Suspendida', false, 4],
            ['TERMINADA', 'Terminada', true, 5],
            ['ANULADA', 'Anulada', true, 6],
        ];

        $estados = [];

        foreach ($datos as [$codigo, $nombre, $esFinal, $posicion]) {
            $estados[$codigo] = EstadoExpediente::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'codigo' => $codigo],
                ['nombre' => $nombre, 'es_final' => $esFinal, 'posicion' => $posicion],
            );
        }

        return $estados;
    }

    /**
     * @return array{administrador: Usuario, abogados: array<string, Abogado>, clientes: array<string, Cliente>}
     */
    private function personas(string $tenantId): array
    {
        $administrador = Usuario::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'correo' => 'admin@estudio-demo.cl'],
            [
                'subject' => 'authentik|estudio-demo-administrador',
                'nombre' => 'Administrador Demo',
                'rol' => 'administrador',
            ],
        );

        $abogados = [];

        foreach (['AND' => 'Andrea Soto', 'BEN' => 'Bastián Núñez'] as $clave => $nombreCompleta) {
            [$nombre, $apellidos] = explode(' ', $nombreCompleta, 2);

            $usuario = Usuario::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'correo' => strtolower($clave) . '@estudio-demo.cl'],
                [
                    'subject' => 'authentik|estudio-demo-' . strtolower($clave),
                    'nombre' => $nombreCompleta,
                    'rol' => $clave === 'AND' ? 'socio' : 'abogado',
                ],
            );

            $abogados[$clave] = Abogado::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'matricula' => 'DEMO-' . $clave],
                [
                    'usuario_id' => $usuario->id,
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'horas_facturables' => 160,
                    'activo' => true,
                ],
            );
        }

        $clientes = [];

        foreach (['CAR' => 'Carla Pineda', 'DIE' => 'Diego Marín'] as $clave => $nombre) {
            [$n, $a] = explode(' ', $nombre, 2);

            $clientes[$clave] = Cliente::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'documento_identidad' => '11111111-1'],
                ['tipo' => 'persona_natural', 'nombre' => $nombre, 'activo' => true],
            );
        }

        return ['administrador' => $administrador, 'abogados' => $abogados, 'clientes' => $clientes];
    }

    /**
     * @return array<string, Expediente>
     */
    private function expedientes(
        string $tenantId,
        array $materias,
        array $estados,
        array $personas,
    ): array {
        $datos = [
            [
                'clave' => 'C-1234-2026-4521',
                'folio' => 'EXP-2026-0001',
                'materia' => 'MAT-LAB',
                'abogado' => 'AND',
                'estado' => 'TRAMITACION',
                'cliente' => 'CAR',
                'titulo' => 'Despido sin carta de aviso',
                'inicio' => '2026-04-08',
            ],
            [
                'clave' => 'C-5567-2026-1188',
                'folio' => 'EXP-2026-0002',
                'materia' => 'MAT-CIV',
                'abogado' => 'BEN',
                'estado' => 'REVISION',
                'cliente' => 'DIE',
                'titulo' => 'Incumplimiento de contrato de arrendamiento',
                'inicio' => '2026-06-22',
            ],
            [
                'clave' => 'C-9012-2026-0077',
                'folio' => 'EXP-2026-0003',
                'materia' => 'MAT-FAM',
                'abogado' => 'AND',
                'estado' => 'INGRESO',
                'cliente' => 'CAR',
                'titulo' => 'Partición de herencias',
                'inicio' => '2026-09-01',
            ],
        ];

        $expedientes = [];

        foreach ($datos as $fila) {
            $expediente = Expediente::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'folio' => $fila['folio']],
                [
                    'numero_causa' => $fila['clave'],
                    'materia_id' => $materias[$fila['materia']]->id,
                    'abogado_responsable_id' => $personas['abogados'][$fila['abogado']]->id,
                    'estado_id' => $estados[$fila['estado']]->id,
                    'cliente_id' => $personas['clientes'][$fila['cliente']]->id,
                    'titulo' => $fila['titulo'],
                    'detalle' => 'Expediente de ejemplo, cargado por la semilla.',
                    'fecha_inicio' => $fila['inicio'],
                ],
            );

            // LAS PARTES. El rol está en la relación, no en la persona: la
            // misma persona puede aparecer como demandada en una causa y como
            // cliente en otra.
            ExpedienteParte::query()->firstOrCreate(
                [
                    'expediente_id' => $expediente->id,
                    'cliente_id' => $personas['clientes'][$fila['cliente']]->id,
                    'rol' => RolParte::Demandante->value,
                ],
                [
                    'tenant_id' => $tenantId,
                    'es_externo' => false,
                ],
            );

            $expedientes[$fila['clave']] = $expediente;
        }

        // Un tercero externo, para que se vea el caso de la parte que no es
        // cliente del estudio.
        $demandada = Cliente::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'documento_identidad' => '22222222-2'],
            ['tipo' => 'persona_natural', 'nombre' => 'Empresa Contratista SpA', 'activo' => true],
        );

        ExpedienteParte::query()->firstOrCreate(
            [
                'expediente_id' => $expedientes['C-1234-2026-4521']->id,
                'cliente_id' => $demandada->id,
                'rol' => RolParte::Demandado->value,
            ],
            [
                'tenant_id' => $tenantId,
                'es_externo' => true,
                'notas' => 'Contraparte. La razón social se anotó a mano y se coteja con el registro de empresas.',
            ],
        );

        return $expedientes;
    }

    /**
     * @param  array<string, Expediente>  $expedientes
     */
    private function plazos(string $tenantId, array $expedientes, ConfiguracionEstudio $configuracion): void
    {
        // LAS FECHAS DE ACÁ SON ESCRITAS, NO CALCULADAS.
        //
        // No hay "30 días desde hoy" en ninguna parte de este archivo. Hay una
        // fecha literal y, al lado, el texto que la respalda. Si cambia el
        // calendario, hay que cambiar estas líneas a mano, y eso es exactamente
        // lo que tiene que pasar: que nadie suponga que el sistema sabe
        // computar plazos.
        $datos = [
            [
                'clave' => 'C-1234-2026-4521',
                'titulo' => 'Contestar la demanda',
                'fecha' => '2026-05-04',
                'respaldo' => 'Plazo contado desde la notificación de la demanda, según lo resuelto por el tribunal '
                    . 'el 2026-03-30. Cómputo hecho a mano por el abogado a cargo; el sistema no lo recalcula.',
            ],
            [
                'clave' => 'C-1234-2026-4521',
                'titulo' => 'Presentar prueba documental',
                'fecha' => '2026-11-14',
                'respaldo' => 'Fecha fijada por el tribunal en la resolución del 2026-10-30 para el Away.',
                'estado' => 'vencido',
            ],
            [
                'clave' => 'C-5567-2026-1188',
                'titulo' => 'Responder la demanda interpuesta',
                'fecha' => '2026-07-20',
                'respaldo' => 'Fecha que el abogado a cargo anotó al recibir la demanda. El cómputo está en su cuaderno, '
                    . 'no en el sistema.',
                'estado' => 'vencido',
            ],
            [
                'clave' => 'C-5567-2026-1188',
                'titulo' => 'Cumplir la audiencia convenida',
                'fecha' => '2027-01-18',
                'respaldo' => 'Fecha acordada por las partes en la mediation del 2026-09-12.',
            ],
            [
                'clave' => 'C-9012-2026-0077',
                'titulo' => 'Reunir el inventario de bienes',
                'fecha' => '2026-10-31',
                'respaldo' => 'Fecha interna del estudio, fijada por la socia a cargo.',
                // SIN ALERTA: este plazo no tiene días de aviso. El sistema no
                // lo marca nunca, aunque su fecha ya haya pasado.
                'sin_alerta' => true,
            ],
        ];

        foreach ($datos as $fila) {
            $expediente = $expedientes[$fila['clave']];

            $plazo = Plazo::query()->firstOrNew([
                'expediente_id' => $expediente->id,
                'titulo' => $fila['titulo'],
            ]);

            if ($plazo->exists) {
                continue;
            }

            $plazo->tenant_id = $tenantId;
            $plazo->fecha_vencimiento = $fila['fecha'];
            $plazo->texto_respaldo = $fila['respaldo'];
            $plazo->estado = $fila['estado'] ?? EstadoPlazo::Pendiente->value;

            if (($fila['sin_alerta'] ?? false) === true) {
                $plazo->dias_alerta = null;
                $plazo->dias_alerta_critica = null;
            } else {
                $plazo->dias_alerta = $configuracion->dias_alerta_plazo;
                $plazo->dias_alerta_critica = $configuracion->dias_alerta_critica;
            }

            if ($plazo->estado === EstadoPlazo::Vencido->value) {
                $plazo->vencido_detectado_at = now();
            }

            $plazo->save();

            // El respaldo se copia al expediente como texto inmutable. Esa es la
            // parte que más importa: si el plazo se corrige, el texto original
            // sigue ahí.
            $plazo->copiarRespaldoAlExpediente();
        }
    }

    /**
     * @param  array<string, Expediente>  $expedientes
     * @param  array<string, TipoDocumento>  $tipos
     */
    private function documentos(string $tenantId, array $expedientes, array $tipos): void
    {
        $datos = [
            [
                'clave' => 'C-1234-2026-4521',
                'natural' => 'CONTRATO-2026-0412',
                'titulo' => 'Contrato de trabajo, versión 1',
                'tipo' => 'TIP-CON',
                'contenido' => 'contrato de trabajo, primera versión, firmado el 2026-01-15',
                'version' => 1,
            ],
            [
                'clave' => 'C-1234-2026-4521',
                'natural' => 'CONTRATO-2026-0412',
                'titulo' => 'Contrato de trabajo, versión 2',
                'tipo' => 'TIP-CON',
                'contenido' => 'contrato de trabajo, segunda versión, con la corrección de laRemuneración',
                'version' => 2,
            ],
            [
                'clave' => 'C-5567-2026-1188',
                'natural' => 'ARREND-5522',
                'titulo' => 'Contrato de arrendamiento',
                'tipo' => 'TIP-CON',
                'contenido' => 'contrato de arrendamiento del inmueble, primera edicion',
                'version' => 1,
            ],
        ];

        foreach ($datos as $fila) {
            $expediente = $expedientes[$fila['clave']];

            $documento = Documento::query()->firstOrCreate(
                ['expediente_id' => $expediente->id, 'clave_natural' => $fila['natural']],
                [
                    'tenant_id' => $tenantId,
                    'tipo_documento_id' => $tipos[$fila['tipo']]->id,
                    'titulo' => $fila['titulo'],
                    'huella_sha256' => hash('sha256', $fila['contenido']),
                    'version_actual' => $fila['version'],
                    'ruta_archivo' => 'demo/' . $expediente->id . '/' . $fila['natural'] . '-v' . $fila['version'],
                    'estado' => 'vigente',
                ],
            );

            DocumentoVersion::query()->firstOrCreate(
                ['documento_id' => $documento->id, 'version' => $fila['version']],
                [
                    'tenant_id' => $tenantId,
                    'huella_sha256' => hash('sha256', $fila['contenido']),
                    'ruta_archivo' => $documento->ruta_archivo,
                    'tamano_bytes' => strlen($fila['contenido']),
                    'recibido_en' => now(),
                    'origen' => 'semilla',
                    'estado' => 'vigente',
                    'resultado_recepcion' => 'nuevo',
                ],
            );
        }
    }

    /**
     * @param  array<string, Expediente>  $expedientes
     * @param  array<string, Abogado>  $abogados
     */
    private function horas(string $tenantId, array $expedientes, array $abogados): void
    {
        $datos = [
            ['C-1234-2026-4521', 'AND', 'Revisión de la demanda y notas de estrategia', 95],
            ['C-1234-2026-4521', 'AND', 'Redacción de la contestación', 240],
            ['C-1234-2026-4521', 'BEN', 'Revisión de prueba documental', 55],
            ['C-5567-2026-1188', 'BEN', 'Estudio del contrato de arrendamiento', 80],
            ['C-5567-2026-1188', 'BEN', 'Preparación de la respuesta', 130],
        ];

        foreach ($datos as [$clave, $abogado, $titulo, $minutos]) {
            \App\Models\Tarea::query()->firstOrCreate(
                [
                    'expediente_id' => $expedientes[$clave]->id,
                    'abogado_id' => $abogados[$abogado]->id,
                    'fecha' => now()->subDays(3)->format('Y-m-d'),
                ],
                [
                    'tenant_id' => $tenantId,
                    'titulo' => $titulo,
                    'fecha' => now()->subDays(3)->format('Y-m-d'),
                    'minutos_imputados' => $minutos,
                    'facturable' => true,
                    'registrado_en' => now(),
                ],
            );
        }
    }
}
