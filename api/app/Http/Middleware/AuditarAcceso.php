<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Expediente;
use App\Services\Tenancy\AuditorDeAcceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audita el acceso a un expediente.
 *
 * Se aplica sobre las rutas que muestran un expediente concreto. La idea es
 * que TODA consulta a un expediente quede registrada, no solo las que alguien
 * recuerde auditar.
 *
 * Si el expediente no existe para esa persona, la peticion ya fue detenida por
 * la Policy o por la RLS. Este middleware solo registra los accesos que
 * ocurrieron.
 */
class AuditarAcceso
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $expediente = $request->route('expediente');

        if ($expediente instanceof Expediente) {
            $accion = $respuesta->getStatusCode() >= 400 ? 'intento_denegado' : 'consultar';

            $this->auditor->registrarExpediente($request, $expediente, $accion);
        }

        return $respuesta;
    }
}