<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudio;
use App\Models\Usuario;
use App\Services\Auth\ProveedorOidcAuthentik;
use App\Services\Auth\VerificadorIdToken;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Inicio y cierre de sesion con Authentik.
 *
 * QUIEN DECIDE QUE ENTRA
 * ----------------------
 * El proveedor de identidad sabe QUIEN es la persona. NO sabe si esa persona
 * puede entrar a este estudio: esa decisión está en `usuarios`, que es la tabla
 * que el estudio administra.
 *
 * Si el `sub` del token no está dado de alta en el estudio, la respuesta es 403
 * y no se crea ninguna cuenta. Un estudio abierto al mundo no es un estudio.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly ProveedorOidcAuthentik $proveedor,
        private readonly VerificadorIdToken $verificador,
        private readonly ContextoTenant $contexto,
    ) {}

    /**
     * SELECCIÓN DE ESTUDIO
     *
     * Antes de iniciar sesión se lee `vw_estudios_publicos`, que expone tres
     * columnas y solo estudios activos. No se lee la tabla `estudios` cruda.
     */
    public function elegirEstudio(): JsonResponse
    {
        $estudios = DB::table('vw_estudios_publicos')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['slug', 'nombre']);

        return response()->json(['estudios' => $estudios]);
    }

    public function entrar(Request $request, string $slug): RedirectResponse
    {
        $estudio = Estudio::query()->where('slug', $slug)->where('activo', true)->first();

        abort_if($estudio === null, 404, 'El estudio no existe o está inactivo.');

        return redirect()->away($this->proveedor->urlDeAutorizacion($slug));
    }

    /**
     * Callback del proveedor.
     *
     * Si la verificación del token falla, LANZA excepción. No se crea una
     * sesión a medias ni se acepta un token sin comprobar: una excepción es
     * ruidosa y el log dice exactamente qué pasó.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $afirmaciones = $this->verificador->verificar(
                $request->query('state'),
                $request->query('code'),
            );
        } catch (RuntimeException $e) {
            Log::warning('estudio: fallo la verificación del token de identidad', [
                'motivo' => $e->getMessage(),
            ]);

            return redirect()->route('entrar')->with('error', 'No se pudo verificar la identidad.');
        }

        // El estudio sale del ESTADO CIFRADO que creó esta misma aplicación al
        // pedir la URL de autorización. No sale de un campo del formulario, que
        // sería modificable.
        $estudio = Estudio::query()
            ->where('slug', $afirmaciones['slug_estudio'])
            ->where('activo', true)
            ->first();

        if ($estudio === null) {
            return redirect()->route('entrar')->with('error', 'El estudio no existe o está inactivo.');
        }

        $usuario = Usuario::query()
            ->where('subject', $afirmaciones['sub'])
            ->where('tenant_id', $estudio->id)
            ->first();

        abort_if(
            $usuario === null,
            403,
            'Esta cuenta no está dada de alta en el estudio.',
        );

        Auth::login($usuario, remember: false);

        $usuario->ultimo_acceso = now();
        $usuario->save();

        return redirect()->to(config('app.url') . '/');
    }

    public function salir(): RedirectResponse
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('entrar');
    }

    public function yo(Request $request): JsonResponse
    {
        $usuario = $request->user();

        abort_if($usuario === null, 401);

        return response()->json([
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->correo,
            'rol' => $usuario->rol->value,
            'rol_legible' => $usuario->rol->etiqueta(),
            've_cartera_completa' => $usuario->veCarteraCompleta(),
            'abogado_id' => $usuario->abogado?->id,
        ]);
    }
}