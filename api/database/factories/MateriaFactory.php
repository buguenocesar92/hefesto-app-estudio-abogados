<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Materia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Materia>
 */
class MateriaFactory extends Factory
{
    protected $model = Materia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->randomElement([
            'Civil',
            'Laboral',
            'Familia y Sucesiones',
            'Penal',
            'Tributario',
        ]);

        return [
            'codigo' => strtoupper(str_replace(' ', '-', $nombre)) . '-' . $this->faker->unique()->numberBetween(1, 999),
            'nombre' => $nombre,
            'activo' => true,
        ];
    }
}