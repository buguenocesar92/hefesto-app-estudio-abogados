<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Plazos\NivelPlazo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expediente: una causa. EL CENTRO DEL SISTEMA.
 *
 * De el cuelgan las partes, los plazos, los documentos y las horas. Si hay que
 * explicar este sistema en una frase, es esto.
 *
 * SOBRE EL NUMERO DE CAUSA
 * ------------------------
 * Lo lleva el tribunal o el estudio y lo coloca alguien a mano. Este sistema NO
 * genera numeros de causa: inventar uno es exactamente el tipo de dato que un
 * programa no puede suponer.
 *
 * SOBRE LA CARTERA
 * ----------------
 * `abogado_responsable_id` define de quien es la causa. Un abogado con rol
 * `abogado` solo ve las suyas. Ver `ExpedientePolicy`.
 *
 * @property string $folio
 * @property string|null $numero_causa
 * @property string $titulo
 * @property \Illuminate\Support\Carbon $fecha_inicio
 */
class Expediente extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'folio',
        'numero_causa',
        'cliente_id',
        'materia_id',
        'abogado_responsable_id',
        'estado_id',
        'titulo',
        'detalle',
        'fecha_inicio',
        'fecha_cierre',
    ];

    protected $casts = ['fecha_inicio' => 'date', 'fecha_cierre' => 'date'];

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Materia, $this> */
    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /** @return BelongsTo<Abogado, $this> */
    public function abogadoResponsable(): BelongsTo
    {
        return $this->belongsTo(Abogado::class, 'abogado_responsable_id');
    }

    /** @return BelongsTo<EstadoExpediente, $this> */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoExpediente::class, 'estado_id');
    }

    /** @return HasMany<ExpedienteParte, $this> */
    public function partes(): HasMany
    {
        return $this->hasMany(ExpedienteParte::class);
    }

    /** @return HasMany<Plazo, $this> */
    public function plazos(): HasMany
    {
        return $this->hasMany(Plazo::class);
    }

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /** @return HasMany<Tarea, $this> */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    /** @return HasMany<IncidenciaDocumental, $this> */
    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaDocumental::class);
    }

    /**
     * Si este abogado es el responsable de la causa.
     *
     * Es el filtro que hace que un abogado no vea la cartera de otro.
     */
    public function esCarteraDe(?Abogado $abogado): bool
    {
        return $abogado !== null && $this->abogado_responsable_id === $abogado->id;
    }

    /**
     * El nivel de alerta de sus plazos, ya evaluado.
     *
     * Es una COMPARACION de la fecha que escribió el abogado con la de hoy.
     * No calcula ningún termino. Ver `NivelPlazo`.
     */
    public function nivelesDePlazos(?string $fechaReferencia = null): array
    {
        $hoy = $fechaReferencia ?? now()->toDateString();

        $resultados = [];

        foreach ($this->plazos as $plazo) {
            $resultados[$plazo->id] = NivelPlazo::evaluar(
                $plazo->fecha_vencimiento instanceof \DateTimeInterface
                    ? $plazo->fecha_vencimiento->format('Y-m-d')
                    : (string) $plazo->fecha_vencimiento,
                $hoy,
                $plazo->dias_alerta,
                $plazo->dias_alerta_critica,
            );
        }

        return $resultados;
    }
}