<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documentos\RecibirDocumentoRequest;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\IncidenciaDocumental;
use App\Services\Documentos\ReceptorDeDocumento;
use App\Services\Tenancy\AuditorDeAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Documentos: asociarlos al expediente y avisar cuando algo no encaja.
 *
 * EL FLUJO COMPLETO
 * -----------------
 * 1. Se sube el archivo por `store`. Se calcula la huella.
 * 2. `ReceptorDeDocumento` decide qué hacer con la copia, mirando la clave
 *    natural, la huella y la versión.
 * 3. Según lo que decida, guarda una versión nueva, no guarda nada, o levanta
 *    una incidencia.
 * 4. La respuesta dice SIEMPRE qué pasó. No devuelve "listo" cuando en realidad
 *    hubo una colisión.
 *
 * RUTAS QUE NO EXISTEN
 * --------------------
 * No hay `destroy()`. Ni para documentos ni para versiones. Un documento mal
 * incorporado se anula. Ver `DocumentosNoSeBoranTest`.
 */
class DocumentoController extends Controller
{
    public function __construct(
        private readonly ReceptorDeDocumento $receptor,
        private readonly AuditorDeAcceso $auditor,
    ) {}

    public function index(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        return response()->json(
            $expediente->documentos()
                ->with(['tipo', 'versiones'])
                ->orderBy('titulo')
                ->get(),
        );
    }

    /**
     * Recibir una copia de documento.
     *
     * La huella se calcula ACA, en el servidor, y no se acepta desde el
     * formulario. Si el cliente mandara la huella, podría mandar "la misma" para
     * un contenido distinto y toda la detección de colisiones sería decorativa.
     */
    public function store(RecibirDocumentoRequest $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('registrar', $expediente);

        $datos = $request->validated();

        $contenido = (string) file_get_contents($request->file('archivo')->getRealPath());
        $huella = $this->receptor->calcularHuella($contenido);

        $ruta = $this->receptor->construirRuta(
            $expediente->id,
            $datos['clave_natural'],
            (string) $datos['version_declarada'],
        );

        Storage::disk('documentos')->put($ruta, $contenido);

        $resultado = $this->receptor->recibir([
            'expediente_id' => $expediente->id,
            'tipo_documento_id' => $datos['tipo_documento_id'] ?? null,
            'clave_natural' => $datos['clave_natural'],
            'titulo' => $datos['titulo'],
            'huella_sha256' => $huella,
            'ruta_archivo' => $ruta,
            'contenido_binario' => $contenido,
            'fecha_documento' => $datos['fecha_documento'] ?? null,
            'version_declarada' => (int) $datos['version_declarada'],
            'origen' => 'carga_manual',
        ], $expediente->tenant_id, $request->user()->id);

        $this->auditor->registrar(
            $request,
            'expediente',
            $expediente->id,
            'recibir_documento',
            sprintf(
                'Copia de "%s" (clave %s, version %d) en el expediente %s: %s',
                $datos['titulo'],
                $datos['clave_natural'],
                $datos['version_declarada'],
                $expediente->folio,
                $resultado['diagnostico']->resultado->etiqueta(),
            ),
        );

        // El codigo de respuesta dice que pasó: 201 cuando se guardo algo
        // nuevo, 200 cuando no se guardo nada pero quedo constancia.
        $codigo = $resultado['diagnostico']->huboCambioEnElDocumento ? 201 : 200;

        return response()->json([
            'resultado' => $resultado['diagnostico']->resultado->value,
            'resultado_legible' => $resultado['diagnostico']->resultado->etiqueta(),
            'explicacion' => $resultado['diagnostico']->explicacion,
            'se_guardo_alguna_version' => $resultado['diagnostico']->huboCambioEnElDocumento,
            'documento' => $resultado['documento'],
            'incidencia' => $resultado['incidencia'],
        ], $codigo);
    }

    /**
     * Incidencias del expediente, para que se puedan revisar.
     */
    public function incidencias(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        return response()->json(
            $expediente->incidencias()
                ->with(['documento', 'expedienteConflictivo'])
                ->orderByDesc('created_at')
                ->get(),
        );
    }

    /**
     * Marcar una incidencia como revisada.
     */
    public function revisarIncidencia(Request $request, IncidenciaDocumental $incidencia): JsonResponse
    {
        $incidencia->revisada = true;
        $incidencia->revisada_at = now();
        $incidencia->revisada_por_usuario_id = $request->user()->id;
        $incidencia->save();

        return response()->json($incidencia);
    }

    /**
     * Descargar una versión.
     *
     * La autorización va antes de tocar el disco: primero se resuelve el
     * documento desde la base (donde la RLS ya filtro), después se comprueba
     * que la ruta no se salga del volumen.
     */
    public function descargar(Expediente $expediente, Documento $documento): JsonResponse
    {
        abort_unless($documento->expediente_id === $expediente->id, 404);

        $this->authorize('view', $documento);

        $ruta = $documento->rutaAbsoluta();

        $base = realpath((string) config('estudio.documentos_path'));

        // Si `documentos_path` no existe todavia, `realpath` devuelve false y
        // esta comprobacion aborta. Es lo correcto: es mejor fallar aca que
        // servir un archivo de cualquier parte del disco.
        abort_if($base === false, 500, 'La carpeta de documentos no está disponible.');

        $rutaReal = realpath($ruta);

        abort_if(
            $rutaReal === false || ! str_starts_with($rutaReal, $base),
            404,
            'El archivo no se encontró.',
        );

        $this->auditor->registrar(
            request(),
            'documento',
            $documento->id,
            'descargar',
            sprintf(
                'Descarga del documento "%s" (clave %s) del expediente %s.',
                $documento->titulo,
                $documento->clave_natural,
                $expediente->folio,
            ),
        );

        return response()->download($rutaReal);
    }
}