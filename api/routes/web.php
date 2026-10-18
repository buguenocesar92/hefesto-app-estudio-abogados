<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\ExpedienteController;
use App\Http\Controllers\Api\PlazoController;
use App\Http\Controllers\Api\TableroController;
use App\Http\Controllers\Api\TareaController;
use App\Http\Middleware\AuditarAcceso;
use App\Http\Middleware\ResolverTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| Todas las rutas de negocio viven en el grupo `web`, que lleva `auth` y
| `ResolverTenant`. El orden importa: `ResolverTenant` envuelve la petición en
| una transaccion con el estudio fijo, y esa transaccion es lo que hace que la
| RLS pueda filtrar.
|
| CSRF: el grupo `web` lo aplica. La interfaz Nuxt manda el token en la
| cabecera `X-CSRF-TOKEN`, que es lo que espera Laravel.
|
*/

Route::middleware(['web', ResolverTenant::class])->prefix('api/v1')->group(function (): void {

    // -----------------------------------------------------------------------
    // Identidad
    // -----------------------------------------------------------------------
    Route::get('auth/estudios', [AuthController::class, 'elegirEstudio'])->name('estudios');

    Route::get('auth/entrar/{slug}', [AuthController::class, 'entrar'])->name('entrar');

    Route::get('auth/callback', [AuthController::class, 'callback'])->name('callback');

    Route::middleware('auth')->group(function (): void {
        Route::post('auth/salir', [AuthController::class, 'salir'])->name('salir');
        Route::get('auth/yo', [AuthController::class, 'yo'])->name('yo');

        // -------------------------------------------------------------------
        // Tablero
        // -------------------------------------------------------------------
        Route::get('tablero', [TableroController::class, 'index']);
        Route::get('plazos', [TableroController::class, 'plazos'])->name('plazos.global');

        // -------------------------------------------------------------------
        // Expedientes
        // -------------------------------------------------------------------
        Route::get('expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
        Route::post('expedientes', [ExpedienteController::class, 'store'])->name('expedientes.store');

        // `AuditarAcceso` va en las rutas que muestran UNA causa. Así queda
        // registrado quién abrió qué, sin depender de que alguien se acuerde.
        Route::get('expedientes/{expediente}', [ExpedienteController::class, 'show'])
            ->middleware(AuditarAcceso::class)
            ->name('expedientes.show');

        Route::put('expedientes/{expediente}', [ExpedienteController::class, 'update'])
            ->name('expedientes.update');

        // -------------------------------------------------------------------
        // RUTAS QUE NO EXISTEN
        // -------------------------------------------------------------------
        // No hay `DELETE /expedientes/{id}`. Un expediente mal abierto se anula:
        //   POST /expedientes/{id}/anular
        Route::post('expedientes/{expediente}/anular', [ExpedienteController::class, 'anular'])
            ->name('expedientes.anular');

        // -------------------------------------------------------------------
        // Plazos
        // -------------------------------------------------------------------
        Route::get('expedientes/{expediente}/plazos', [PlazoController::class, 'index']);
        Route::post('expedientes/{expediente}/plazos', [PlazoController::class, 'store'])
            ->name('plazos.store');

        Route::get('expedientes/{expediente}/plazos/{plazo}', [PlazoController::class, 'show']);

        // -------------------------------------------------------------------
        // RUTAS QUE NO EXISTEN
        // -------------------------------------------------------------------
        // No hay `DELETE` de plazos, ni ruta para cambiar el texto de respaldo,
        // ni ruta para cambiar la fecha de vencimiento. El respaldo es
        // inmutable y eso lo garantiza un disparador de la base.
        Route::post('expedientes/{expediente}/plazos/{plazo}/cumplir', [PlazoController::class, 'cumplir'])
            ->name('plazos.cumplir');

        // -------------------------------------------------------------------
        // Documentos
        // -------------------------------------------------------------------
        Route::get('expedientes/{expediente}/documentos', [DocumentoController::class, 'index']);
        Route::post('expedientes/{expediente}/documentos', [DocumentoController::class, 'store'])
            ->name('documentos.store');

        Route::get('expedientes/{expediente}/documentos/{documento}/descargar', [DocumentoController::class, 'descargar'])
            ->name('documentos.descargar');

        Route::get('expedientes/{expediente}/incidencias', [DocumentoController::class, 'incidencias'])
            ->name('incidencias.index');

        Route::post('incidencias/{incidencia}/revisar', [DocumentoController::class, 'revisarIncidencia'])
            ->name('incidencias.revisar');

        // -------------------------------------------------------------------
        // RUTAS QUE NO EXISTEN
        // -------------------------------------------------------------------
        // No hay `DELETE /expedientes/{id}/documentos/{id}` ni de sus versiones.
        // Para dejar de usar un documento mal incorporado se anula. Dos pruebas
        // vigilan que esas rutas no aparezcan:
        //   DocumentosNoSeBoranTest
        //   ExpedientesNoSeBoranTest

        // -------------------------------------------------------------------
        // Clientes y partes
        // -------------------------------------------------------------------
        Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::put('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');

        Route::post('expedientes/{expediente}/partes/{cliente}', [ClienteController::class, 'agregarParte'])
            ->name('partes.store');

        // -------------------------------------------------------------------
        // Tareas y horas
        // -------------------------------------------------------------------
        Route::get('expedientes/{expediente}/tareas', [TareaController::class, 'index']);
        Route::post('expedientes/{expediente}/tareas', [TareaController::class, 'store'])
            ->name('tareas.store');
        Route::get('expedientes/{expediente}/tareas/resumen', [TareaController::class, 'resumen'])
            ->name('tareas.resumen');
        Route::post('tareas/{tarea}/facturar', [TareaController::class, 'facturar'])
            ->name('tareas.facturar');

        // -------------------------------------------------------------------
        // Configuracion
        // -------------------------------------------------------------------
        Route::get('configuracion', [ConfiguracionController::class, 'mostrar'])->name('configuracion.mostrar');
        Route::put('configuracion', [ConfiguracionController::class, 'actualizar'])->name('configuracion.actualizar');
        Route::get('estudio', [ConfiguracionController::class, 'estudio'])->name('estudio');
    });
});

// Salud, sin sesion y sin transaccion. Si tocara una tabla protegida,
// `app_tenant_id()` lanzaria excepcion: el aislamiento falla cerrado.
Route::get('api/salud', function () {
    return response()->json([
        'estado' => 'ok',
        'nota' => 'Este sistema no calcula plazos legales ni interpreta la ley.',
    ]);
})->name('salud');