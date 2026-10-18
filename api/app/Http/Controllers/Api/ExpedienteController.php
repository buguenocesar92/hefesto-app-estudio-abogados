<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expedientes\AbrirExpedienteRequest;
use App\Models\Abogado;
use App\Models\Cliente;
use App\Models\Expediente;
use App\Models\Materia;
use App\Services\Tenancy\AuditorDeAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Expedientes: el centro del sistema.
 *
 * RUTAS QUE NO EXISTEN
 * --------------------
 * En este controlador no hay `destroy()`. Un expediente mal abierto se anula.
 * No hay forma de borrar una causa desde la API, y eso no es una omisión: es
 * una decisión. Ver docs/09.
 */
class ExpedienteController extends Controller
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function index()
    {
        $usuario = request()->user();

        $expedientes = Expediente::query()
            ->when(! $usuario->rol->veCarteraCompleta(), function ($consulta) use ($usuario): void {
                // La cartera se filtra en la consulta, no despues.
                $consulta->where('abogado_responsable_id', $usuario->abogado?->id);
            })
            ->with(['estado', 'materia', 'abogadoResponsable', 'cliente'])
            ->orderByDesc('fecha_inicio')
            ->paginate(30);

        return response()->json($expedientes);
    }

    public function show(Expediente $expediente)
    {
        $this->authorize('view', $expediente);

        $expediente->load(['estado', 'materia', 'abogadoResponsable', 'cliente']);

        $this->auditor->registrarExpediente(request(), $expediente);

        return response()->json([
            'datos' => $expediente,
            'plazos' => $expediente->plazos()->orderBy('fecha_vencimiento')->get(),
            'documentos' => $expediente->documentos()->with('versiones')->get(),
            'partes' => $expediente->partes()->with('cliente')->get(),
            'tareas' => $expediente->tareas()->with('abogado')->get(),
            'incidencias' => $expediente->incidencias()->get(),
        ]);
    }

    /**
     * Abrir un expediente.
     *
     * El `folio` es interno del estudio y lo genera la aplicación. El
     * `numero_causa` NO se genera: lo escribe la persona.
     */
    public function store(AbrirExpedienteRequest $request): JsonResponse
    {
        $usuario = $request->user();

        $datos = $request->validated();

        $expediente = DB::transaction(function () use ($datos, $usuario): Expediente {
            $expediente = Expediente::query()->create([
                'tenant_id' => $usuario->tenant_id,
                'folio' => $this->siguienteFolio($usuario->tenant_id),
                'numero_causa' => $datos['numero_causa'] ?? null,
                'cliente_id' => $datos['cliente_id'] ?? null,
                'materia_id' => $datos['materia_id'],
                'abogado_responsable_id' => $datos['abogado_responsable_id'] ?? null,
                'titulo' => $datos['titulo'],
                'detalle' => $datos['detalle'] ?? null,
                'fecha_inicio' => $datos['fecha_inicio'],
            ]);

            // Si la causa trae partes, se registran acá. El rol va en la
            // relacion, no en la persona.
            foreach ($datos['partes'] ?? [] as $parte) {
                $expediente->partes()->create([
                    'tenant_id' => $usuario->tenant_id,
                    'cliente_id' => $parte['cliente_id'],
                    'rol' => $parte['rol'],
                    'es_externo' => $parte['es_externo'] ?? false,
                    'notas' => $parte['notas'] ?? null,
                ]);
            }

            return $expediente;
        });

        $this->auditor->registrar(
            $request,
            'expediente',
            $expediente->id,
            'crear',
            sprintf('Expediente %s abierto: %s', $expediente->folio, $expediente->titulo),
        );

        return response()->json($expediente, 201);
    }

    public function update(AbrirExpedienteRequest $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('update', $expediente);

        $datos = $request->validated();

        // El folio y el numero de causa no se tocan en una edicion.
        $expediente->fill([
            'cliente_id' => $datos['cliente_id'] ?? $expediente->cliente_id,
            'materia_id' => $datos['materia_id'],
            'abogado_responsable_id' => $datos['abogado_responsable_id'] ?? null,
            'titulo' => $datos['titulo'],
            'detalle' => $datos['detalle'] ?? null,
        ])->save();

        return response()->json($expediente);
    }

    /**
     * Anular un expediente. No borrarlo.
     *
     * Se cambia el estado a uno final del catálogo y se escribe el motivo. La
     * causa sigue existiendo con todo su historial.
     */
    public function anular(Expediente $expediente, Request $request): JsonResponse
    {
        $this->authorize('update', $expediente);

        $motivo = $request->input('motivo');

        abort_if($motivo === null || mb_strlen(trim($motivo)) < 5, 422, 'Escriba el motivo de la anulación.');

        $estadoFinal = $request->user()->tenant_id
            ? \App\Models\EstadoExpediente::query()
                ->where('tenant_id', $expediente->tenant_id)
                ->where('es_final', true)
                ->first()
            : null;

        abort_if($estadoFinal === null, 422, 'El estudio no tiene un estado final configurado.');

        $expediente->estado_id = $estadoFinal->id;
        $expediente->fecha_cierre = now()->toDateString();
        $expediente->detalle = trim((string) $expediente->detalle . "\n\n[ANULADO] " . $motivo);
        $expediente->save();

        $this->auditor->registrar(
            $request,
            'expediente',
            $expediente->id,
            'anular',
            sprintf('Expediente %s anulado: %s', $expediente->folio, $motivo),
        );

        return response()->json($expediente);
    }

    /**
     * Folio interno del estudio.
     *
     * Se genera en la aplicación porque no significa nada fuera de acá, al
     * contrario que el número de causa, que no se genera jamás.
     */
    private function siguienteFolio(string $tenantId): string
    {
        $anio = now()->year;
        $prefijo = 'EXP-' . $anio . '-';

        $ultimo = Expediente::query()
            ->where('tenant_id', $tenantId)
            ->where('folio', 'like', $prefijo . '%')
            ->orderByDesc('folio')
            ->value('folio');

        $siguiente = $ultimo === null
            ? 1
            : ((int) Str::afterLast($ultimo, '-')) + 1;

        return $prefijo . str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }
}