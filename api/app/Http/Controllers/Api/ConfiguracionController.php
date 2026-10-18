<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionEstudio;
use App\Models\Estudio;
use App\Models\Materia;
use App\Models\TipoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuracion del estudio y catalogos.
 *
 * LO QUE SE CONFIGURA
 * -------------------
 * Dias de aviso de plazos. Un numero OPERATIVO que elige el socio: cuantos
 * dias antes de la fecha que escribio el abogado aparece un aviso.
 *
 * LO QUE NO SE CONFIGURA
 * ----------------------
 * Ningun plazo legal. En ninguna pantalla de esta aplicacion hay un campo que
 * diga "dias para contestar" o "dias para apelar". No es una omision que se
 * complete mas adelante: es una decision. Un sistema que calcula un termino
 * afirma una cosa juridica con una certeza que no tiene, y si la norma cambia
 * el abogado pierde un termino.
 *
 * Si `dias_alerta_plazo` queda en NULL, el sistema no marca ningun plazo. La
 * pantalla lo dice.
 */
class ConfiguracionController extends Controller
{
    public function mostrar(Request $request): JsonResponse
    {
        $configuracion = ConfiguracionEstudio::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->first();

        return response()->json([
            'configuracion' => $configuracion,
            'materias' => Materia::query()->where('tenant_id', $request->user()->tenant_id)->orderBy('nombre')->get(),
            'tipos_documento' => TipoDocumento::query()->where('tenant_id', $request->user()->tenant_id)->orderBy('nombre')->get(),
            'aviso' => 'Los días de aviso son una alarma interna del estudio. No son plazos legales y no se usan para calcular vencimientos.',
        ]);
    }

    public function actualizar(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $this->authorize('crear', Materia::class);

        $datos = $request->validate([
            'zona_horaria' => ['sometimes', 'timezone'],
            'moneda' => ['sometimes', 'string', 'size:3'],
            'valor_hora_imputable' => ['nullable', 'integer', 'min:0'],
            'dias_alerta_plazo' => ['nullable', 'integer', 'min:0', 'max:365'],
            'dias_alerta_critica' => ['nullable', 'integer', 'min:0', 'max:365'],
            'avisar_documento_duplicado' => ['nullable', 'boolean'],
        ]);

        // Si se configura la ventana crítica pero no la ventana de aviso, el
        // sistema no podría decir nada. Se rechaza en el formulario en vez de
        // dejar una configuración que no hace falta explicar.
        if (
            isset($datos['dias_alerta_critica'])
            && $datos['dias_alerta_critica'] !== null
            && ($datos['dias_alerta_plazo'] ?? null) === null
        ) {
            return response()->json([
                'mensaje' => 'Para usar una ventana crítica hay que configurar antes la ventana de aviso.',
                'errores' => ['dias_alerta_plazo' => ['Configure los días de aviso primero.']],
            ], 422);
        }

        $configuracion = ConfiguracionEstudio::query()->firstOrNew(['tenant_id' => $usuario->tenant_id]);

        $configuracion->fill($datos);
        $configuracion->tenant_id = $usuario->tenant_id;
        $configuracion->save();

        return response()->json([
            'configuracion' => $configuracion,
            'mensaje' => $configuracion->tieneAlertaDePlazos()
                ? 'Los plazos se marcarán desde la fecha registrada. Sigue sin haber ningún cálculo de plazos.'
                : 'Sin días de aviso no se marca ningún plazo. Los plazos se siguen registrando y se siguen viendo.',
        ]);
    }

    /**
     * Identidad del estudio, para la cabecera de la interfaz.
     */
    public function estudio(Request $request): JsonResponse
    {
        $estudio = Estudio::query()->findOrFail($request->user()->tenant_id);

        return response()->json([
            'id' => $estudio->id,
            'nombre' => $estudio->nombre,
            'slug' => $estudio->slug,
        ]);
    }
}