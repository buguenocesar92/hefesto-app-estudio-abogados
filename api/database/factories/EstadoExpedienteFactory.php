<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EstadoExpediente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstadoExpediente>
 */
class EstadoExpedienteFactory extends Factory
{
    protected $model = EstadoExpediente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper($this->faker->unique()->lexify('?????')),
            'nombre' => $this->faker->unique()->word(),
            'es_final' => false,
            'posicion' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function final_(): static
    {
        return $this->state(fn (): array => ['es_final' => true]);
    }
}