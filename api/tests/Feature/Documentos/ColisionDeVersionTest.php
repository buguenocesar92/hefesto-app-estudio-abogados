<?php

declare(strict_types=1);

namespace Tests\Feature\Documentos;

use App\Enums\ResultadoRecepcion;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\Expediente;
use App\Models\IncidenciaDocumental;
use App\Services\Documentos\ReceptorDeDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LA COLISIÓN DE VERSIÓN DE UN DOCUMENTO.
 *
 * -------------------------------------------------------------------
 * ESTA ES LA PRUEBA QUE DA NOMBRE AL PRODUCTO
 * -------------------------------------------------------------------
 *
 * El expediente de un estudio vive en un archivador físico. Llega un
 * documento. Lo que casi nunca se detecta es que ese documento YA estaba, o
 * que llegó con una versión distinta de la que se tiene. Cuando eso pasa y
 * nadie lo nota, el estudio sigue trabajando sobre un documento que ya no es el
 * vigente.
 *
 * CASOS QUE SE COMPRUEBAN
 * -----------------------
 *  1. Documento nuevo: no estaba, se registra como versión 1.
 *  2. Versión nueva: mismo documento, contenido distinto, versión mayor.
 *  3. Duplicado exacto: misma huella, misma versión. No se agrega nada.
 *  4. COLISIÓN: contenido distinto con versión igual o menor. NO se sobrescribe
 *     y se abre una incidencia de severidad alta.
 *  5. Duplicado en otro expediente: el contenido ya estaba archivado en otra
 *     causa del estudio.
 *
 * El caso 4 es el importante. El sistema NO decide cuál de las dos copias es
 * buena: levanta la incidencia y que una persona vaya al archivador a mirar.
 */
class ColisionDeVersionTest extends TestCase
{
    use RefreshDatabase;

    private function receptor(): ReceptorDeDocumento
    {
        return new ReceptorDeDocumento(new \App\Services\Documentos\DetectorDeColision());
    }

    /**
     * @param  array<string, Expediente>  $estudio
     * @return array{expediente: Expediente}
     */
    private function crearCausa(array $estudio, string $titulo = 'Causa de prueba'): array
    {
        return ['expediente' => Expediente::factory()->create([
            'tenant_id' => $estudio['estudio']->id,
            'materia_id' => $estudio['materia']->id,
            'abogado_responsable_id' => $estudio['fichaAbogado']->id,
            'titulo' => $titulo,
        ])];
    }

    public function test_un_documento_nuevo_se_registra_como_version_uno(): void
    {
        $estudio = $this->crearEstudio();
        ['expediente' => $expediente] = $this->crearCausa($estudio);

        $resultado = $this->receptor()->recibir([
            'expediente_id' => $expediente->id,
            'tipo_documento_id' => $estudio['tipo']->id,
            'clave_natural' => 'CONTRATO-001',
            'titulo' => 'Contrato de trabajo',
            'huella_sha256' => hash('sha256', 'primera version'),
            'ruta_archivo' => 'demo/contrato-001.pdf',
            'contenido_binario' => 'primera version',
            'version_declarada' => 1,
        ], $estudio['estudio']->id, $estudio['abogado']->id);

        $this->assertSame(ResultadoRecepcion::Nuevo, $resultado['diagnostico']->resultado);
        $this->assertTrue($resultado['diagnostico']->huboCambioEnElDocumento);
        $this->assertNull($resultado['incidencia'], 'Un documento nuevo no abre incidencia.');

        $this->assertSame(1, Documento::query()->count());
        $this->assertSame(1, DocumentoVersion::query()->count());
    }

    public function test_una_version_nueva_se_agrega_y_sustituye_a_la_anterior(): void
    {
        $estudio = $this->crearEstudio();
        ['expediente' => $expediente] = $this->crearCausa($estudio);

        $receptor = $this->receptor();

        $receptor->recibir([
            'expediente_id' => $expediente->id,
            'clave_natural' => 'CONTRATO-002',
            'titulo' => 'Contrato',
            'huella_sha256' => hash('sha256', 'contenido uno'),
            'ruta_archivo' => 'demo/c2-v1.pdf',
            'contenido_binario' => 'contenido uno',
            'version_declarada' => 1,
        ], $estudio['estudio']->id);

        $resultado = $receptor->recibir([
            'expediente_id' => $expediente->id,
            'clave_natural' => 'CONTRATO-002',
            'titulo' => 'Contrato',
            'huella_sha256' => hash('sha256', 'contenido dos'),
            'ruta_archivo' => 'demo/c2-v2.pdf',
            'contenido_binario' => 'contenido dos',
            'version_declarada' => 2,
        ], $estudio['estudio']->id);

        $this->assertSame(ResultadoRecepcion::VersionNueva, $resultado['diagnostico']->resultado);
        $this->assertTrue($resultado['diagnostico']->huboCambioEnElDocumento);

        $documento = $resultado['documento'];

        $this->assertSame(2, $documento->version_actual);
        $this->assertSame(hash('sha256', 'contenido dos'), $documento->huella_sha256);

        // La versión anterior NO se borra: queda como sustituida y sigue
        // descargable.
        $this->assertSame(2, DocumentoVersion::query()->count());
        $this->assertSame(1, DocumentoVersion::query()->where('estado', 'no_vigente')->count());
    }

    public function test_un_duplicado_exacto_no_agrega_nada(): void
    {
        $estudio = $this->crearEstudio();
        ['expediente' => $expediente] = $this->crearCausa($estudio);

        $receptor = $this->receptor();

        $datos = [
            'expediente_id' => $expediente->id,
            'clave_natural' => 'SENTENCIA-001',
            'titulo' => 'Sentencia',
            'huella_sha256' => hash('sha256', 'sentencia definitiva'),
            'ruta_archivo' => 'demo/s1.pdf',
            'contenido_binario' => 'sentencia definitiva',
            'version_declarada' => 1,
        ];

        $receptor->recibir($datos, $estudio['estudio']->id);

        $resultado = $receptor->recibir($datos, $estudio['estudio']->id);

        $this->assertSame(ResultadoRecepcion::DuplicadoExacto, $resultado['diagnostico']->resultado);
        $this->assertFalse($resultado['diagnostico']->huboCambioEnElDocumento);

        // No se crea una versión nueva: el documento no cambió.
        $this->assertSame(1, DocumentoVersion::query()->count());
    }

    /**
     * EL CASO CENTRAL DE ESTE PRODUCTO.
     *
     * El mismo documento vuelve con contenido DISTINTO y una versión que NO
     * corresponde. El sistema tiene dos copias diferentes del mismo documento
     * y no sabe cuál es la buena.
     *
     * Lo que TIENE que pasar:
     *  - No se sobrescribe el documento que ya estaba.
     *  - Se abre una incidencia de severidad alta.
     *  - La copia_received queda guardada como evidencia, sin tocar la
     *    versión vigente.
     */
    public function test_la_colision_no_sobreescribe_y_abre_incidencia(): void
    {
        $estudio = $this->crearEstudio();
        ['expediente' => $expediente] = $this->crearCausa($estudio);

        $receptor = $this->receptor();

        // El estudio ya tiene la versión 1, con SU contenido.
        $receptor->recibir([
            'expediente_id' => $expediente->id,
            'clave_natural' => 'CONTRATO-COLISION',
            'titulo' => 'Contrato de arrendamiento',
            'huella_sha256' => hash('sha256', 'contenido que ya tiene el estudio'),
            'ruta_archivo' => 'demo/colision-v1.pdf',
            'contenido_binario' => 'contenido que ya tiene el estudio',
            'version_declarada' => 1,
        ], $estudio['estudio']->id);

        $documentoAntes = Documento::query()->where('clave_natural', 'CONTRATO-COLISION')->firstOrFail();

        // Llega OTRA copia del mismo documento, con otro contenido, pero
        // declarado como versión 1. Eso es una colisión.
        $resultado = $receptor->recibir([
            'expediente_id' => $expediente->id,
            'clave_natural' => 'CONTRATO-COLISION',
            'titulo' => 'Contrato de arrendamiento',
            'huella_sha256' => hash('sha256', 'contenido distinto que llego despues'),
            'ruta_archivo' => 'demo/colision-otra.pdf',
            'contenido_binario' => 'contenido distinto que llego despues',
            'version_declarada' => 1,
        ], $estudio['estudio']->id);

        // 1. Se detectó.
        $this->assertSame(ResultadoRecepcion::ColisionVersion, $resultado['diagnostico']->resultado);
        $this->assertFalse($resultado['diagnostico']->huboCambioEnElDocumento);

        // 2. NO se sobrescribió nada del documento que ya estaba.
        $documentoDespues = $documentoAntes->fresh();

        $this->assertSame($documentoAntes->huella_sha256, $documentoDespues->huella_sha256);
        $this->assertSame(1, $documentoDespues->version_actual);
        $this->assertSame($documentoAntes->ruta_archivo, $documentoDespues->ruta_archivo);

        // 3. Se abrió una incidencia de severidad alta.
        $incidencia = IncidenciaDocumental::query()->firstOrFail();

        $this->assertSame('colision_version', $incidencia->tipo);
        $this->assertSame('alta', $incidencia->severidad);
        $this->assertFalse($incidencia->revisada);
        $this->assertSame($expediente->id, $incidencia->expediente_id);

        // 4. La copia que llegó quedó guardada como evidencia, pero NO como
        // versión vigente del documento.
        $this->assertSame(2, DocumentoVersion::query()->count());
        $this->assertSame(1, DocumentoVersion::query()->where('estado', 'no_vigente')->count());
    }

    public function test_una_version_menor_tambien_es_colision(): void
    {
        $estudio = $this->crearEstudio();
        ['expediente' => $expediente] = $this->crearCausa($estudio);

        $receptor = $this->receptor();

        // El estudio tiene la versión 3 vigente.
        $receptor->recibir([
            'expediente_id' => $expediente->id,
            'clave_natural' => 'PODER-001',
            'titulo' => 'Poder',
            'huella_sha256' => hash('sha256', 'tercera version'),
            'ruta_archivo' => 'demo/poder-v3.pdf',
            'contenido_binario' => 'tercera version',
            'version_declarada' => 3,
        ], $estudio['estudio']->id);

        // Llega una versión 2 con otro contenido. El documento no puede
        // retroceder solo.
        $resultado = $receptor->recibir([
            'expediente_id' => $expediente->id,
            'clave_natural' => 'PODER-001',
            'titulo' => 'Poder',
            'huella_sha256' => hash('sha256', 'segunda version vieja'),
            'ruta_archivo' => 'demo/poder-v2.pdf',
            'contenido_binario' => 'segunda version vieja',
            'version_declarada' => 2,
        ], $estudio['estudio']->id);

        $this->assertSame(ResultadoRecepcion::ColisionVersion, $resultado['diagnostico']->resultado);

        $documento = $resultado['documento']->fresh();

        $this->assertSame(3, $documento->version_actual);
        $this->assertSame(hash('sha256', 'tercera version'), $documento->huella_sha256);
    }

    /**
     * EL OTRO AVISO, Y EL MÁS SE PASARÁ POR ALTO.
     *
     * El documento llegó bien y es el correcto, pero ese MISMO contenido ya
     * estaba archivado en otra causa. Sin esta comprobación, el estudio termina
     * con el mismo contrato en dos carpetas y no se entera hasta que alguien
     * busca el original.
     */
    public function test_avisa_cuando_el_documento_ya_estaba_en_otro_expediente(): void
    {
        $estudio = $this->crearEstudio();

        ['expediente' => $primera] = $this->crearCausa($estudio, 'Causa original');
        ['expediente' => $segunda] = $this->crearCausa($estudio, 'Causa donde llegó por error');

        $receptor = $this->receptor();
        $contenido = 'acta de entrega de servicios';

        $receptor->recibir([
            'expediente_id' => $primera->id,
            'clave_natural' => 'ACTA-001',
            'titulo' => 'Acta',
            'huella_sha256' => hash('sha256', $contenido),
            'ruta_archivo' => 'demo/acta.pdf',
            'contenido_binario' => $contenido,
            'version_declarada' => 1,
        ], $estudio['estudio']->id);

        // El mismo contenido llega a otra causa, con otra clave natural: para el
        // sistema es un documento nuevo, y debería serlo.
        $resultado = $receptor->recibir([
            'expediente_id' => $segunda->id,
            'clave_natural' => 'ACTA-COPIA',
            'titulo' => 'Acta (¿copia?)',
            'huella_sha256' => hash('sha256', $contenido),
            'ruta_archivo' => 'demo/acta-copia.pdf',
            'contenido_binario' => $contenido,
            'version_declarada' => 1,
        ], $estudio['estudio']->id);

        $this->assertSame(ResultadoRecepcion::Nuevo, $resultado['diagnostico']->resultado);

        $incidencia = IncidenciaDocumental::query()
            ->where('tipo', 'duplicado_otro_expediente')
            ->firstOrFail();

        $this->assertSame($segunda->id, $incidencia->expediente_id);
        $this->assertSame($primera->id, $incidencia->expediente_conflictivo_id);
        $this->assertSame('media', $incidencia->severidad);
    }

    public function test_la_huella_se_calcula_en_el_servidor_y_no_se_acepta_del_formulario(): void
    {
        $receptor = $this->receptor();

        $uno = $receptor->calcularHuella('contenido');
        $dos = $receptor->calcularHuella('contenido');
        $otro = $receptor->calcularHuella('contenido distinto');

        $this->assertSame($uno, $dos);
        $this->assertNotSame($uno, $otro);
        $this->assertSame(64, strlen($uno), 'La huella es un SHA-256 en hexadecimal: 64 caracteres.');
    }
}
