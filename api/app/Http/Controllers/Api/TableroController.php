<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Services\Plazos\NivelPlazo;
use App\Services\Tenancy\AuditorDeAcceso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tablero: la pantalla que el abogado abre primero.
 *
 * MARCA DE AGUA EN LA RESPUESTA
 * -----------------------------
 * Este controlador no calcula plazos. Toma la fecha que escribió cada abogado y
 * la compara con la de hoy, usando los días de aviso que el estudio configuró.
 *
 * Si un plazo no tiene días de aviso configurados, su nivel es
 * `sin_alerta` y NO se marca. El tablero lo dice así de forma expresa, para que
 * nadie lea un gris como "todo bien".
 */
class TableroController extends Controller
{
    public function __construct(private readonly AuditorDeAcceso $auditor) {}

    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $hoy = now()->toDateString();

        // El filtro de cartera va AQUÍ, en la consulta, y no en la vista.
        // Filtrar después de traer significa haber recibido los datos de la
        // cartera ajena en memoria.
        $expedientes = Expediente::query()
            ->when(! $usuario->rol->veCarteraCompleta(), function ($consulta) use ($usuario): void {
                $abogado = $usuario->abogado;

                $consulta->where('abogado_responsable_id', $abogado?->id);
            })
            ->with(['estado', 'materia', 'abogadoResponsable'])
            ->orderByDesc('fecha_inicio')
            ->limit(50)
            ->get();

        $cantidadAlertas = 0;
        $filas = [];

        foreach ($expedientes as $expediente) {
            $niveles = $expediente->nivelesDePlazos($hoy);

            $vencidos = 0;
            $proximos = 0;
            $sinAlerta = 0;

            foreach ($niveles as $nivel) {
                if ($nivel->requiereAviso()) {
                    $vencidos++;
                }

                if ($nivel->nivel === \App\Enums\NivelAlertaPlazo::Proximo) {
                    $proximos++;
                }

                if (! $nivel->marcado()) {
                    $sinAlerta++;
                }
            }

            $cantidadAlertas += $vencidos;

            $filas[] = [
                'folio' => $expediente->folio,
                'numero_causa' => $expediente->numero_causa,
                'titulo' => $expediente->titulo,
                'materia' => $expediente->materia?->nombre,
                'abogado' => $expediente->abogadoResponsable?->nombreCompleto(),
                'estado' => $expediente->estado?->nombre,
                'plazos_vencidos' => $vencidos,
                'plazos_proximos' => $proximos,
                'plazos_sin_alerta' => $sinAlerta,
            ];
        }

        return response()->json([
            'fecha_referencia' => $hoy,
            'advertencia' => 'Las fechas las escribió cada abogado. Este sistema las vigila, no las calcula.',
            'alertas' => $cantidadAlertas,
            'expedientes' => $filas,
        ]);
    }

    /**
     * Plazos que exigen atención, ya evaluados.
     *
     * Devuelve el nivel y el texto de respaldo: un tablero donde solo se ve la
     * fecha no sirve, porque lo que hay que mirar es la razón.
     */
    public function plazos(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $hoy = now()->toDateString();

        $plazos = Plazo::query()
            ->when(! $usuario->rol->veCarteraCompleta(), function ($consulta) use ($usuario): void {
                $consulta->whereHas(
                    'expediente',
                    fn ($q) => $q->where('abogado_responsable_id', $usuario->abogado?->id),
                );
            })
            ->whereNotNull('dias_alerta')
            ->whereIn('estado', ['pendiente', 'vencido'])
            ->with(['expediente', 'expediente.abogadoResponsable'])
            ->orderBy('fecha_vencimiento')
            ->limit(200)
            ->get();

        $salida = [];

        foreach ($plazos as $plazo) {
            $nivel = NivelPlazo::evaluar(
                $plazo->fecha_vencimiento->format('Y-m-d'),
                $hoy,
                $plazo->dias_alerta,
                $plazo->dias_alerta_critica,
            );

            $salida[] = [
                'id' => $plazo->id,
                'titulo' => $plazo->titulo,
                'fecha_vencimiento' => $plazo->fecha_vencimiento->format('Y-m-d'),
                'texto_respaldo' => $plazo->texto_respaldo,
                'estado' => $plazo->estado->value,
                'nivel' => $nivel->nivel->value,
                'color' => $nivel->nivel->color(),
                'resumen' => $nivel->resumen(),
                'expediente' => $plazo->expediente->folio,
                'abogado' => $plazo->expediente->abogadoResponsable?->nombreCompleto(),
            ];
        }

        return response()->json([
            'fecha_referencia' => $hoy,
            'plazos' => $salida,
        ]);
    }
}