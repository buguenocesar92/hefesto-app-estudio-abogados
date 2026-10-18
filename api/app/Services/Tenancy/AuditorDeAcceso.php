<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\BitacoraAcceso;
use App\Models\Expediente;
use Illuminate\Http\Request;

/**
 * Registro de acceso a expedientes.
 *
 * POR QUE ESTO ES OBLIGATORIO Y NO OPCIONAL
 * -----------------------------------------
 * En un estudio de abogados, saber quien consulto un expediente no es una
 * funcion de auditoria: es la diferencia entre una perdida de confianza y un
 * incidente con nombre y apellido. Un nombre dentro de un expediente basta para
 * danar a alguien, y la Ley 19.628 obliga a poder demostrar quien accedio a los
 * datos.
 *
 * Ademas hay un caso concreto que da niesto a este modulo: la consulta de un
 * expediente que la persona no era responsable. El sistema lo bloquea, pero que
 * lo haya intentado es justamente lo que hay que saber.
 *
 * SOLO SE AGREGAN FILAS
 * ---------------------
 * No hay ruta de actualizacion ni de borrado en toda la aplicacion, y la
 * migracion 2026_10_17_000019 deja la garantia en la base con un disparador.
 */
class AuditorDeAcceso
{
    public function registrar(
        Request $request,
        string $recurso,
        string $recursoId,
        string $accion,
        string $resumen,
        ?string $tenantId = null,
        ?string $usuarioId = null,
    ): void {
        $usuario = $request->user();

        BitacoraAcceso::query()->create([
            'tenant_id' => $tenantId ?? $usuario?->tenant_id,
            'usuario_id' => $usuarioId ?? $usuario?->id,
            'recurso' => $recurso,
            'recurso_id' => $recursoId,
            'resumen' => mb_substr($resumen, 0, 300),
            'accion' => $accion,
            'direccion_ip' => $request->ip(),
            'agente' => mb_substr((string) $request->userAgent(), 0, 300),
            'ocurrido_en' => now(),
        ]);
    }

    /** Acceso a un expediente concreto. Es el caso mas frecuente. */
    public function registrarExpediente(
        Request $request,
        Expediente $expediente,
        string $accion = 'consultar',
    ): void {
        $this->registrar(
            $request,
            'expediente',
            $expediente->id,
            $accion,
            sprintf(
                'Expediente %s (%s) - %s',
                $expediente->folio,
                $expediente->numero_causa ?? 'sin numero de causa',
                $expediente->titulo,
            ),
            $expediente->tenant_id,
        );
    }

    /**
     * Intento de acceso denegado.
     *
     * Se registra aunque el recurso NO exista para esa persona. Si no, el
     * registro solo guardaria los accesos correctos, y justamente el caso
     * interesante es el que se intento y no se pudo.
     */
    public function registrarIntentoDenegado(
        Request $request,
        string $recurso,
        string $recursoId,
        string $motivo,
    ): void {
        $usuario = $request->user();

        BitacoraAcceso::query()->create([
            'tenant_id' => $usuario?->tenant_id,
            'usuario_id' => $usuario?->id,
            'recurso' => $recurso,
            'recurso_id' => $recursoId,
            'resumen' => mb_substr('Acceso denegado: ' . $motivo, 0, 300),
            'accion' => 'acceso_denegado',
            'direccion_ip' => $request->ip(),
            'agente' => mb_substr((string) $request->userAgent(), 0, 300),
            'ocurrido_en' => now(),
        ]);
    }
}