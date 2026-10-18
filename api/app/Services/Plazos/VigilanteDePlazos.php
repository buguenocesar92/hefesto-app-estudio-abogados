<?php

declare(strict_types=1);

namespace App\Services\Plazos;

use App\Enums\EstadoPlazo;
use App\Enums\NivelAlertaPlazo;
use App\Models\Plazo;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Support\Facades\DB;

/**
 * EL VIGILANTE: repasa los plazos y marca los que ya vencieron.
 *
 * QUÉ HACE, EN UNA FRASE
 * ----------------------
 * Compara la fecha que escribió el abogado con la fecha de hoy, y a los que ya
 * pasaron les pone `estado = vencido`. Nada más.
 *
 * QUÉ NO HACE, Y NO VA A HACER
 * ----------------------------
 * No calcula vencimientos. No deriva una fecha de otra. No sabe si un plazo
 * corre en días hábiles o corridos, ni aplica feriados, ni revocatoria.
 * Nada de eso está en el sistema y no se va a inventar.
 *
 * Si el estudio no escribió la fecha, no hay plazo que vigilar. No hay forma de
 * que este servicio se rellene una.
 *
 * POR QUÉ EL ESTADO CAMBIANDO Y NO SE CALCULA AL LEER
 * -----------------------------------------------------
 * Para que se pueda preguntar "¿cuándo nos dimos cuenta?". Un tablero que
 * calcula el color al mostrar responde "hoy está rojo" y no dice desde cuándo.
 * Guardar `vencido_detectado_at` deja constancia de que el sistema lo verá el
 * martes, aunque la fecha haya pasado el lunes.
 *
 * Y para que el vigilante se pueda correr en cualquier momento, incluso dos
 * veces el mismo día, sin efectos raros.
 *
 * CUANDO UN PLAZO YA VENCIDO, QUEDA VENCIDO
 * ------------------------------------------
 * Si un abogado marca un plazo como `cumplido` después de que venció, el
 * servicio no lo devuelve a `pendiente`: respeta lo que la persona decidió. Un
 * plazo puede haberse cumplido con dos días de atraso, y eso es información
 * que el estudio necesita, no un error que haya que esconder.
 */
class VigilanteDePlazos
{
    public function __construct(private readonly ContextoTenant $contexto) {}

    /**
     * Marca los vencidos de UN estudio.
     *
     * Se ejecuta DENTRO de una transacción con el contexto de estudio fijo:
     * sin eso, la consulta no tendría contra qué filtrar y la política RLS
     * lanzaría una excepción. Es la misma razón por la que el middleware
     * `ResolverTenant` envuelve cada petición.
     *
     * @return array{revisados: int, marcados: int, sin_alerta: int}
     */
    public function marcarVencidos(string $tenantId, ?string $fechaReferencia = null): array
    {
        return $this->contexto->ejecutarAislado(
            $tenantId,
            fn (): array => $this->marcarDentroDelEstudio($fechaReferencia),
        );
    }

    /**
     * @return array{revisados: int, marcados: int, sin_alerta: int}
     */
    private function marcarDentroDelEstudio(?string $fechaReferencia): array
    {
        $hoy = $fechaReferencia ?? now()->toDateString();

        $revisados = 0;
        $marcados = 0;
        $sinAlerta = 0;

        // Se recorre de a uno y no con un `UPDATE` masivo a propósito: cada
        // plazo puede tener su propio número de días (es el snapshot del
        // momento en que se creó), así que la decisión es individual.
        $pendientes = Plazo::query()
            ->whereIn('estado', [EstadoPlazo::Pendiente->value, EstadoPlazo::Vencido->value])
            ->orderBy('fecha_vencimiento')
            ->cursor();

        foreach ($pendientes as $plazo) {
            $revisados++;

            $nivel = $this->evaluar($plazo, $hoy);

            // Sin número configurado, el plazo no se toca. Ni se marca ni se
            // desmarca. Un tablero con huecos se nota; uno con colores
            // inventados se usa.
            if ($nivel->nivel === NivelAlertaPlazo::SinAlerta) {
                $sinAlerta++;

                continue;
            }

            $deberiaEstarVencido = $nivel->nivel === NivelAlertaPlazo::Vencido;

            if ($deberiaEstarVencido && $plazo->estado !== EstadoPlazo::Vencido->value) {
                $plazo->estado = EstadoPlazo::Vencido->value;

                // Sólo la primera vez que se detecta. Si el vigilante corre
                // mañana otra vez, esta marca no se pisa: la fecha en que el
                // estudio se enteró no cambia por haberlo visto otra vez.
                $plazo->vencido_detectado_at = now();

                $plazo->save();

                $marcados++;
            }
        }

        return ['revisados' => $revisados, 'marcados' => $marcados, 'sin_alerta' => $sinAlerta];
    }

    /**
     * Evalúa un plazo con SUS números, no con los actuales del estudio.
     *
     * Usa `dias_alerta` del plazo, que es el snapshot del momento en que se
     * creó. Si se usara el valor actual de la configuración, cambiar el número
     * del estudio reescribiría el pasado de todos los plazos: un plazo creado
     * en enero quedaría marcado según lo que el socio decidió en junio.
     */
    public function evaluar(Plazo $plazo, string $fechaReferencia): NivelPlazo
    {
        return NivelPlazo::evaluar(
            $plazo->fecha_vencimiento instanceof \DateTimeInterface
                ? $plazo->fecha_vencimiento->format('Y-m-d')
                : (string) $plazo->fecha_vencimiento,
            $fechaReferencia,
            $plazo->dias_alerta,
            $plazo->dias_alerta_critica,
        );
    }

    /**
     * Plazos que exigen atención, para el tablero.
     *
     * Sólo devuelve los que TIENEN número configurado. Un plazo sin número no
     * aparece en el tablero: no hay nada que mirar.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function plazosQueRequierenAviso(string $tenantId, int $limite = 50)
    {
        return $this->contexto->ejecutarAislado(
            $tenantId,
            fn () => Plazo::query()
                ->where('estado', '!=', EstadoPlazo::Cumplido->value)
                ->whereNotNull('dias_alerta')
                ->orderBy('fecha_vencimiento')
                ->limit($limite)
                ->get(),
        );
    }

    /** Reabre un plazo mal marcado. Deja constancia de quién lo hizo. */
    public function devolverAPendiente(Plazo $plazo, int $usuarioId): void
    {
        $plazo->estado = EstadoPlazo::Pendiente->value;
        $plazo->vencido_detectado_at = null;
        $plazo->save();
    }
}
