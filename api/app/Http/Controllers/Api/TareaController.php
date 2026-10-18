<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Abogado;
use App\Models\Expediente;
use App\Models\Tarea;
use App\Services\Tenancy\AuditorDeAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tareas y horas imputables.
 *
 * LO QUE NO HACE ESTA APLICACIÓN
 * ------------------------------
 * No emite documentos tributarios, no timbre, no factura ante el Servicio de
 * Impuestos, y no habrá código que lo haga.
 *
 * `numero_factura` y `fecha_facturacion` son un registro: el estudio escribe
 * acá lo que YA emitió por su cuenta. No es un emisor.
 *
 * LO QUE SÍ HACE
 * ---------------
 * Que las horas queden escritas. El problema clásico de un estudio no es que el
 * abogado no trabaje: es que nadie anotó las horas, y a fin de mes hay trabajo
 * hecho que no está en ninguna parte.
 */
class TareaController extends Controller
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function index(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        return response()->json(
            $expediente->tareas()
                ->with('abogado')
                ->orderByDesc('fecha')
                ->get(),
        );
    }

    /**
     * Resumen de horas del expediente.
     *
     * Suma de minutos y de horas. La conversión de minutos a horas es una
     * división, no un cálculo de plazo.
     */
    public function resumen(Expediente $expediente): JsonResponse
    {
        $this->authorize('view', $expediente);

        $totales = Tarea::query()
            ->where('expediente_id', $expediente->id)
            ->selectRaw('COALESCE(SUM(minutos_imputados), 0) AS minutos')
            ->selectRaw('COALESCE(SUM(CASE WHEN facturable THEN minutos_imputados ELSE 0 END), 0) AS minutos_facturables')
            ->selectRaw('COALESCE(SUM(COALESCE(minutos_facturados, 0)), 0) AS minutos_facturados')
            ->first();

        return response()->json([
            'minutos_imputados' => (int) $totales->minutos,
            'horas_imputadas' => round((int) $totales->minutos / 60, 2),
            'minutos_facturables' => (int) $totales->minutos_facturables,
            'minutos_facturados' => (int) $totales->minutos_facturados,
            'horas_no_cobradas' => round(
                max(0, (int) $totales->minutos_facturables - (int) $totales->minutos_facturados) / 60,
                2,
            ),
        ]);
    }

    public function store(Expediente $expediente, Request $request): JsonResponse
    {
        $this->authorize('registrar', $expediente);

        $datos = $request->validate([
            'abogado_id' => ['required', 'uuid', 'exists:abogados,id'],
            'titulo' => ['required', 'string', 'max:200'],
            'detalle' => ['nullable', 'string', 'max:4000'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            // Un máximo de 24 horas. No es una regla del estudio: es para que
            // un error de tipeo (un año en vez de un día) no se cuele como si
            // fuera real.
            'minutos_imputados' => ['required', 'integer', 'min:1', 'max:1440'],
            'facturable' => ['nullable', 'boolean'],
        ]);

        $tarea = DB::transaction(function () use ($datos, $expediente, $request): Tarea {
            return Tarea::query()->create([
                'tenant_id' => $expediente->tenant_id,
                'expediente_id' => $expediente->id,
                'abogado_id' => $datos['abogado_id'],
                'titulo' => $datos['titulo'],
                'detalle' => $datos['detalle'] ?? null,
                'fecha' => $datos['fecha'],
                'minutos_imputados' => $datos['minutos_imputados'],
                'facturable' => $datos['facturable'] ?? true,
                'registrado_por_usuario_id' => $request->user()->id,
                'registrado_en' => now(),
            ]);
        });

        $this->auditor->registrar(
            $request,
            'expediente',
            $expediente->id,
            'registrar_tarea',
            sprintf(
                'Horas registradas en el expediente %s: %s (%d minutos).',
                $expediente->folio,
                $tarea->titulo,
                $tarea->minutos_imputados,
            ),
        );

        return response()->json($tarea, 201);
    }

    /**
     * Registrar lo que el estudio ya facturó por su cuenta.
     */
    public function facturar(Tarea $tarea, Request $request): JsonResponse
    {
        $this->authorize('actualizar', $tarea);

        $datos = $request->validate([
            'minutos_facturados' => ['required', 'integer', 'min:0'],
            'fecha_facturacion' => ['required', 'date_format:Y-m-d'],
            'numero_factura' => ['nullable', 'string', 'max:40'],
        ]);

        abort_if(
            $datos['minutos_facturados'] > $tarea->minutos_imputados,
            422,
            'No se pueden facturar más minutos de los que se imputaron.',
        );

        $tarea->fill($datos)->save();

        return response()->json($tarea);
    }
}