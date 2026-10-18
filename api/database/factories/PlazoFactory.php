<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoPlazo;
use App\Models\Plazo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plazo>
 */
class PlazoFactory extends Factory
{
    protected $model = Plazo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(3),
            'fecha_vencimiento' => $this->faker->dateTimeBetween('-1 month', '+3 months')->format('Y-m-d'),
            // El respaldo es obligatorio y largo a propósito en la fábrica: es
            // el campo que más se descuidaría en una prueba, y el que después
            // se vuelve inmutable.
            'texto_respaldo' => $this->faker->paragraph(2),
            'estado' => EstadoPlazo::Pendiente->value,
            'dias_alerta' => 7,
            'dias_alerta_critica' => 2,
        ];
    }

    /** Plazo sin días de aviso: el sistema NO lo marca nunca. */
    public function sinAlerta(): static
    {
        return $this->state(fn (): array => [
            'dias_alerta' => null,
            'dias_alerta_critica' => null,
        ]);
    }

    public function vencido(): static
    {
        return $this->state(fn (): array => [
            'fecha_vencimiento' => now()->subDays(3)->format('Y-m-d'),
            'estado' => EstadoPlazo::Pendiente->value,
        ]);
    }
}