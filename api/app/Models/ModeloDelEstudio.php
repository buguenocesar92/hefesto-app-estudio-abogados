<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo modelo que pertenece a un estudio.
 *
 * Aporta tres cosas:
 *
 * 1. Generador de identificadores UUID. No hay enteros auto-incrementales:
 *    en una tabla con datos de clientes, un `id` adivinable permite probar
 *    `/expedientes/41` hasta dar con uno real. Un UUID no.
 *
 * 2. `tenant_id` filled desde el contexto. Una columna forgotten queda en
 *    NULL, y la política RLS con `WITH CHECK` rechaza la fila. Falla cerrado.
 *
 * 3. Un alcance `delEstudio()`, por si alguna consulta necesita ser explicita.
 *
 * En la practica la RLS ya filtra: el alcance es para documentar la intencion
 * y para las consultas donde conviene ser explicito, no para salvar la
 * aplicacion si falta un filtro.
 */
abstract class ModeloDelEstudio extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::creating(function (self $modelo): void {
            if ($modelo->tenant_id === null) {
                $modelo->tenant_id = app(\App\Services\Tenancy\ContextoTenant::class)->tenantIdEnConexion();
            }
        });
    }

    /**
     * Asigna el estudio en el momento de crear la fila, para el caso en que
     * el contexto todavia no esta fijado (semilla, comandos de consola).
     */
    public function nuevoParaEstudio(string $tenantId): static
    {
        $this->tenant_id = $tenantId;

        return $this;
    }
}