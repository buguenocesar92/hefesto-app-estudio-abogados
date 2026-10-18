<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Abogado: la persona que ejerce.
 *
 * El vinculo con la cuenta de inicio de sesion es `usuario_id`. Un abogado
 * puede tener cuenta y puede no tenerla: entra el pasante que carga horas y
 * el socio que solo revisa, y el pasante no necesita ver la cartera.
 *
 * La matricula la lleva el estudio y se escribe a mano. Esta aplicacion NO
 * consulta ningun registro del Colegio de Abogados: no tiene acceso a el y no
 * hay codigo que lo pretenda.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $nombre
 * @property string $apellidos
 * @property string|null $matricula
 * @property int|null $horas_facturables
 */
class Abogado extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'usuario_id',
        'nombre',
        'apellidos',
        'matricula',
        'horas_facturables',
        'activo',
    ];

    protected $casts = ['activo' => 'boolean', 'horas_facturables' => 'integer'];

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class, 'abogado_responsable_id');
    }

    /** @return HasMany<Tarea, $this> */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellidos);
    }
}