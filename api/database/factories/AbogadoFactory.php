<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Abogado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Abogado>
 */
class AbogadoFactory extends Factory
{
    protected $model = Abogado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->firstName(),
            'apellidos' => $this->faker->lastName(),
            'matricula' => strtoupper($this->faker->unique()->bothify('??###')),
            'horas_facturables' => 160,
            'activo' => true,
        ];
    }
}