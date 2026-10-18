<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\ContextoTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el estudio de la peticion para que la RLS sepa a quien pertenece cada
 * fila.
 *
 * Se aplica a todo el grupo `web` ANTES del middleware `auth` de la ruta. Por
 * eso no puede usar `$request->user()` todavia: resuelve el usuario por su
 * cuenta con el guard `web`, que ya lee la sesion.
 *
 * Peticiones sin sesion (login, callback, salud) pasan sin transaccion. Si
 * esas rutas tocaran una tabla protegida, `public.app_tenant_id()` lanzaria
 * excepcion y la peticion fallaria: el aislamiento falla cerrado, nunca
 * abierto.
 */
class ResolverTenant
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = auth()->guard('web')->user();

        if ($usuario === null) {
            return $next($request);
        }

        if ($usuario->tenant_id === null) {
            // Una cuenta sin estudio no puede operar. Se corta antes de tocar la
            // base de datos.
            abort(403, 'La cuenta no esta asociada a ningun estudio.');
        }

        return $this->contexto->ejecutarAislado(
            $usuario->tenant_id,
            static fn () => $next($request),
        );
    }
}