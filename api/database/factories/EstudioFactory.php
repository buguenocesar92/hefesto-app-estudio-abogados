<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Estudio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Estudio>
 */
class EstudioFactory extends Factory
{
    protected $model = Estudio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = 'Estudio ' . $this->faker->lastName();

        return [
            'slug' => 'estudio-' . Str::lower(Str::random(8)),
            'nombre' => $nombre,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}