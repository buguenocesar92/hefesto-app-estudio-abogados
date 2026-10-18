<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPlazo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Plazos\RegistrarPlazoRequest;
use App\Models\ConfiguracionEstudio;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Services\Plazos\NivelPlazo;
use App\Services\Tenancy\AuditorDeAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plazos: registrar y vigilar fechas que escribe el abogado.
 *
 * -------------------------------------------------------------------
 * ESTE CONTROLADOR NO CALCULA PLAZOS LEGALES
 * -------------------------------------------------------------------
 * `fecha_vencimiento` viene del formulario, escrita por la persona. No hay
 * ningun parametro que la derive de otra fecha ni ninguna cantidad de dias que
 * se multiplique. Si el campo viene vacío, la validación lo rechaza: no hay
 * forma de registrar un plazo sin fecha, y un plazo sin fecha no se vigila.
 *
 * `texto_respaldo` es obligatorio y con un mínimo de largo. No porque sea un
 * capricho de formulario: porque la razón por la que esa fecha se escribió es
 * lo que el estudio necesita conservar.
 *
 * RUTAS QUE NO EXISTEN
 * --------------------
 * No hay `destroy()` y no hay ruta para cambiar el texto de respaldo ni la
 * fecha. Un plazo mal escrito se marca como cumplido y se registra otro.
 */
class PlazoController extends Controller
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function index(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        $hoy = now()->toDateString();

        $plazos = $expediente->plazos()->orderBy('fecha_vencimiento')->get();

        $salida = $plazos->map(function (Plazo $plazo) use ($hoy): array {
            $nivel = NivelPlazo::evaluar(
                $plazo->fecha_vencimiento->format('Y-m-d'),
                $hoy,
                $plazo->dias_alerta,
                $plazo->dias_alerta_critica,
            );

            return [
                'id' => $plazo->id,
                'titulo' => $plazo->titulo,
                'fecha_vencimiento' => $plazo->fecha_vencimiento->format('Y-m-d'),
                'texto_respaldo' => $plazo->texto_respaldo,
                'estado' => $plazo->estado->value,
                'nivel' => $nivel->nivel->value,
                'color' => $nivel->nivel->color(),
                'resumen' => $nivel->resumen(),
                'vencido_detectado_at' => $plazo->vencido_detectado_at?->toIso8601String(),
            ];
        });

        return response()->json(['plazos' => $salida]);
    }

    public function store(RegistrarPlazoRequest $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('registrar', $expediente);

        $datos = $request->validated();

        // Se copia la configuración del estudio en el momento de crear el
        // plazo. Los dias de aviso quedan congelados aca: si manana el socio
        // cambia el numero, este plazo conserva el que tenia cuando se
        // escribio la fecha.
        $configuracion = ConfiguracionEstudio::query()
            ->where('tenant_id', $expediente->tenant_id)
            ->first();

        $plazo = Plazo::query()->create([
            'tenant_id' => $expediente->tenant_id,
            'expediente_id' => $expediente->id,
            'titulo' => $datos['titulo'],
            'fecha_vencimiento' => $datos['fecha_vencimiento'],
            'texto_respaldo' => $datos['texto_respaldo'],
            'estado' => EstadoPlazo::Pendiente->value,
            'dias_alerta' => $configuracion?->dias_alerta_plazo,
            'dias_alerta_critica' => $configuracion?->dias_alerta_critica,
        ]);

        // El respaldo se copia al expediente como texto inmutable. Queda ahi
        // aunque el plazo se exporte o se migre a otro sistema.
        $plazo->copiarRespaldoAlExpediente();

        $this->auditor->registrar(
            $request,
            'expediente',
            $expediente->id,
            'registrar_plazo',
            sprintf(
                'Plazo "%s" con fecha %s en el expediente %s.',
                $plazo->titulo,
                $plazo->fecha_vencimiento->format('Y-m-d'),
                $expediente->folio,
            ),
        );

        return response()->json($plazo, 201);
    }

    /**
     * Marcar un plazo como cumplido.
     *
     * Queda quién lo hizo y cuándo. Un plazo cumplido tarde sigue siendo un dato
     * útil: por eso el servicio no esconde los vencidos.
     */
    public function cumplir(Request $request, Expediente $expediente, Plazo $plazo): JsonResponse
    {
        $this->authorize('view', $expediente);

        $plazo->estado = EstadoPlazo::Cumplido->value;
        $plazo->cumplido_at = now();
        $plazo->cumplido_por_usuario_id = $request->user()->id;
        $plazo->save();

        $this->auditor->registrar(
            $request,
            'expediente',
            $expediente->id,
            'cumplir_plazo',
            sprintf(
                'Plazo "%s" (fecha %s) marcado como cumplido.',
                $plazo->titulo,
                $plazo->fecha_vencimiento->format('Y-m-d'),
            ),
        );

        return response()->json($plazo);
    }

    /**
     * Un plazo de otro expediente o de otro estudio: 404, no 403.
     */
    public function show(Expediente $expediente, Plazo $plazo): JsonResponse
    {
        abort_unless($plazo->expediente_id === $expediente->id, 404);

        $this->authorize('view', $expediente);

        return response()->json($plazo);
    }
}
