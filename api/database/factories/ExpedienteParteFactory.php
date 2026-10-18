<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolParte;
use App\Models\ExpedienteParte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpedienteParte>
 */
class ExpedienteParteFactory extends Factory
{
    protected $model = ExpedienteParte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rol' => RolParte::Demandado->value,
            'es_externo' => true,
            'notas' => null,
        ];
    }

    /** Parte del estudio, por ejemplo el cliente. */
    public function delEstudio(): static
    {
        return $this->state(fn (): array => ['es_externo' => false]);
    }
}