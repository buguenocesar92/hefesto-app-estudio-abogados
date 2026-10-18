<?php

declare(strict_types=1);

use App\Services\Plazos\VigilanteDePlazos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| El vigilante de plazos corre una vez al día, temprano. Su ÚNICA función es
| marcar los plazos cuya fecha ya pasó.
|
| LO QUE NO HACE ESTE CRON
| -----------------------
| No calcula ningún vencimiento. No deriva una fecha de otra. No manda correos con
| plazos inventados. Compara la fecha que escribió el abogado con la de hoy.
|
| Si el estudio no configuró días de aviso, la operación no marca nada y cuenta
| cada plazo como `sin_alerta`. Ver `App\Services\Plazos\VigilanteDePlazos`.
|
*/

Schedule::call(function (): void {
    $vigilante = app(VigilanteDePlazos::class);

    // Se recorre estudio por estudio, cada uno con SU contexto. El worker de
    // colas reutiliza la misma conexión para trabajos de estudios distintos, y
    // por eso `ContextoTenant` usa alcance de transacción: al terminar, la
    // conexión no arrastra el estudio anterior.
    $estudios = DB::table('estudios')
        ->where('activo', true)
        ->pluck('id');

    foreach ($estudios as $tenantId) {
        $resultado = $vigilante->marcarVencidos($tenantId);

        if ($resultado['marcados'] > 0) {
            Log::info('estudio: se marcaron plazos vencidos', $resultado + ['tenant_id' => $tenantId]);
        }
    }
})
    ->name('vigilante:plazos')
    ->dailyAt('07:00')
    ->withoutOverlapping();