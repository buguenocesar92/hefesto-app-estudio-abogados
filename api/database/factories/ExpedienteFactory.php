<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expediente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Expediente>
 */
class ExpedienteFactory extends Factory
{
    protected $model = Expediente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folio' => 'EXP-' . $this->faker->unique()->numerify('####'),
            // El número de causa lo escribe una persona. La fábrica pone algo
            // con forma de número de causa, nunca uno "bonito" inventado por el
            // sistema como si fuera real.
            'numero_causa' => 'C-' . $this->faker->numerify('####-####-####'),
            'titulo' => $this->faker->sentence(4),
            'detalle' => $this->faker->paragraph(),
            'fecha_inicio' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }

    public function de(string $tenantId): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenantId]);
    }
}