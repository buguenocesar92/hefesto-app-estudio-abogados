<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\RolParte;
use App\Models\Cliente;
use App\Models\Expediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Clientes: el padrón de personas del estudio.
 *
 * DATOS PERSONALES
 * ----------------
 * Un cliente en un estudio de abogados no es un dato de contacto: es la lista
 * de a quiénes representa este despacho. Se guardan los campos justos y el
 * acceso está filtrado por cartera en `ClientePolicy`.
 */
class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $clientes = Cliente::query()
            ->where('activo', true)
            ->when(! $usuario->rol->veCarteraCompleta(), function ($consulta) use ($usuario): void {
                // Solo los clientes con al menos una causa en su cartera. Un
                // pasante no ve el padrón completo del estudio.
                $consulta->whereHas('expedientes', function ($q) use ($usuario): void {
                    $q->where('abogado_responsable_id', $usuario->abogado?->id);
                });
            })
            ->orderBy('nombre')
            ->paginate(50);

        return response()->json($clientes);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);

        return response()->json($cliente->load(['expedientes', 'participaciones.expediente']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('crear', Cliente::class);

        $datos = $request->validate([
            'tipo' => ['nullable', Rule::in(['persona_natural', 'persona_juridica'])],
            'nombre' => ['required', 'string', 'max:160'],
            'documento_identidad' => ['nullable', 'string', 'max:32'],
            'correo' => ['nullable', 'email', 'max:191'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string', 'max:4000'],
        ]);

        $cliente = Cliente::query()->create($datos + [
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json($cliente, 201);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('actualizar', $cliente);

        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:160'],
            'documento_identidad' => ['nullable', 'string', 'max:32'],
            'correo' => ['nullable', 'email', 'max:191'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string', 'max:4000'],
        ]);

        $cliente->fill($datos)->save();

        return response()->json($cliente);
    }

    /**
     * Vincular una persona a una causa, con su rol.
     *
     * El rol se pide acá, en la relación. La misma persona puede quedar como
     * demandada en una causa y como cliente en otra, y el sistema lo representa
     * sin duplicarla.
     */
    public function agregarParte(Expediente $expediente, Cliente $cliente, Request $request): JsonResponse
    {
        $this->authorize('registrar', $expediente);

        $datos = $request->validate([
            'rol' => ['required', Rule::in(array_column(RolParte::cases(), 'value'))],
            'es_externo' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $parte = $expediente->partes()->create($datos + [
            'tenant_id' => $expediente->tenant_id,
            'cliente_id' => $cliente->id,
        ]);

        return response()->json($parte->load('cliente'), 201);
    }
}