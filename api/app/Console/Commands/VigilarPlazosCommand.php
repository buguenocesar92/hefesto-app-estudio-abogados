<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Estudio;
use App\Models\Plazo;
use App\Services\Plazos\VigilanteDePlazos;
use Illuminate\Console\Command;

/**
 * Corre el vigilante de plazos a mano.
 *
 * ÚTIL CUANDO
 * -----------
 *  - Se acaba de migrar una base que ya tenía plazos vencidos.
 *  - Alguien escribió un plazo con fecha pasada y quiere verlo marcado ya.
 *  - En el despliegue, para comprobar que la operación funciona.
 *
 * LO QUE HACE
 * -----------
 * Compara la fecha de cada plazo con la de hoy y marca los vencidos.
 *
 * LO QUE NO HACE
 * --------------
 * No calcula ningún vencimiento. No inventa fechas. Si el estudio no
 * configuró días de aviso, no marca nada.
 */
class VigilarPlazosCommand extends Command
{
    protected $signature = 'estudio:vigilar-plazos
        {--estudio= :_slug del estudio. Si se omite, se recorren todos los activos.}
        {--fecha= : Fecha de referencia en formato AAAA-MM-DD. Sirve para probar.}';

    protected $description = 'Marca los plazos cuya fecha registrada ya pasó. No calcula ningún vencimiento.';

    public function handle(VigilanteDePlazos $vigilante): int
    {
        $fecha = $this->option('fecha');

        if ($fecha !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->error('La fecha debe escribirse como AAAA-MM-DD.');

            return self::FAILURE;
        }

        $slug = $this->option('estudio');

        if ($slug !== null) {
            $estudio = Estudio::query()->where('slug', $slug)->first();

            if ($estudio === null) {
                $this->error('No existe un estudio con el slug indicado.');

                return self::FAILURE;
            }

            return $this->vigilarUno($vigilante, $estudio->id, $fecha);
        }

        $estudios = Estudio::query()->where('activo', true)->get();

        if ($estudios->isEmpty()) {
            $this->warn('No hay estudios activos.');

            return self::SUCCESS;
        }

        foreach ($estudios as $estudio) {
            $this->vigilarUno($vigilante, $estudio->id, $fecha);
        }

        return self::SUCCESS;
    }

    private function vigilarUno(VigilanteDePlazos $vigilante, string $tenantId, ?string $fecha): int
    {
        $resultado = $vigilante->marcarVencidos($tenantId, $fecha);

        $this->line(sprintf(
            '  revisados %d, marcados %d, sin alerta %d',
            $resultado['revisados'],
            $resultado['marcados'],
            $resultado['sin_alerta'],
        ));

        if ($resultado['sin_alerta'] > 0) {
            $this->line('  Los plazos sin alerta no se tocan: el estudio no configuró días de aviso para ellos.');
        }

        return self::SUCCESS;
    }
}